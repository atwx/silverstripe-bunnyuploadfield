import React, { useState } from 'react';
import PropTypes from 'prop-types';
import fieldHolder from 'components/FieldHolder/FieldHolder';
import { loadComponent } from 'lib/Injector';

function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) return meta.getAttribute('content');
  const field = document.querySelector('input[name="SecurityID"]');
  return field ? field.value : '';
}

function uploadFile(url, file, apiKey, onProgress) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.upload.addEventListener('progress', (e) => {
      if (e.lengthComputable) {
        onProgress(Math.round((e.loaded / e.total) * 100));
      }
    });
    xhr.addEventListener('load', () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve();
      } else {
        reject(new Error(`Upload failed (status ${xhr.status})`));
      }
    });
    xhr.addEventListener('error', () => reject(new Error('Network error during upload')));
    xhr.addEventListener('abort', () => reject(new Error('Upload aborted')));
    xhr.open('PUT', url);
    xhr.setRequestHeader('Content-Type', 'application/octet-stream');
    xhr.setRequestHeader('AccessKey', apiKey);
    xhr.send(file);
  });
}

const BunnyVideoUploadField = ({ id, name, value, onChange, data, disabled, readOnly }) => {
  const { endpoint, libraryId, searchEndpoint, cdnHostname } = data || {};
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);
  const [status, setStatus] = useState(null);
  const [modalOpen, setModalOpen] = useState(false);

  const triggerFileInput = () => {
    document.getElementById(`${id}_file`).click();
  };

  const handleFileSelect = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('video/')) {
      setStatus({ text: 'Please select a video file', type: 'error' });
      return;
    }

    setUploading(true);
    setProgress(0);
    setStatus({ text: 'Preparing...', type: 'info' });

    try {
      const createRes = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify({ title: file.name, libraryId }),
      });

      if (!createRes.ok) {
        const err = await createRes.json();
        throw new Error(err.error || 'Error creating video');
      }

      const { videoId, uploadUrl, apiKey } = await createRes.json();

      setStatus({ text: 'Uploading...', type: 'info' });
      await uploadFile(uploadUrl, file, apiKey, setProgress);

      onChange(videoId);
      setStatus({ text: 'Upload successful! Video is being processed...', type: 'success' });
    } catch (err) {
      setStatus({ text: `Error: ${err.message}`, type: 'error' });
    } finally {
      setUploading(false);
    }
  };

  const handleChooseExisting = (selectedData) => {
    if (selectedData && selectedData.videoId) {
      onChange(selectedData.videoId);
      setStatus(null);
    }
    setModalOpen(false);
  };

  const handleRemove = () => {
    onChange('');
    setStatus(null);
  };

  if (readOnly) {
    if (!value) {
      return <span className="bunny-video-upload-field--empty">—</span>;
    }
    return (
      <div className="bunny-video-upload-field bunny-video-upload-field--readonly">
        <iframe
          src={`https://iframe.mediadelivery.net/embed/${libraryId}/${value}`}
          loading="lazy"
          style={{ border: 0, width: '100%', maxWidth: 640, aspectRatio: '16/9' }}
          allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture"
          allowFullScreen
        />
      </div>
    );
  }

  const CmsModal = modalOpen ? loadComponent('CmsModal') : null;
  const CmsModalSearch = modalOpen ? loadComponent('CmsModalSearch') : null;

  return (
    <div className="bunny-video-upload-field">
      <input type="hidden" name={name} id={id} value={value || ''} readOnly />

      {!value && (
        <div className="uploadfield__dropzone">
          {/* eslint-disable-next-line jsx-a11y/label-has-associated-control */}
          <label htmlFor={`${id}_file`} className="uploadfield__backdrop" aria-hidden="true" />
          <div className="uploadfield__droptext">
            <button
              type="button"
              className="uploadfield__upload-button"
              onClick={triggerFileInput}
              disabled={uploading || disabled}
            >
              Upload new
            </button>
            {' '}or{' '}
            <button
              type="button"
              className="uploadfield__add-button"
              onClick={() => setModalOpen(true)}
              disabled={uploading || disabled}
            >
              Choose existing
            </button>
          </div>
          <input
            type="file"
            id={`${id}_file`}
            accept="video/*"
            style={{ display: 'none' }}
            onChange={handleFileSelect}
            disabled={uploading || disabled}
          />
        </div>
      )}

      {uploading && (
        <div className="bunny-upload-progress">
          <div className="bunny-progress-bar">
            <div className="bunny-progress-fill" style={{ width: `${progress}%` }} />
          </div>
          <div className="bunny-progress-text">{progress}%</div>
        </div>
      )}

      {status && (
        <div className={`upload-status upload-status--${status.type}`}>
          {status.text}
        </div>
      )}

      {value && !uploading && (
        <div className="bunny-current-video">
          <div className="bunny-item">
            <div className="bunny-item__icon" />
            <div className="bunny-item__title">{value}</div>
            <button
              type="button"
              className="bunny-item__remove btn btn-secondary btn-sm"
              onClick={handleRemove}
            >
              Remove video
            </button>
          </div>
          <div className="bunny-video-preview">
            <iframe
              src={`https://iframe.mediadelivery.net/embed/${libraryId}/${value}`}
              loading="lazy"
              style={{ border: 0, width: '100%', aspectRatio: '16/9' }}
              allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture"
              allowFullScreen
            />
          </div>
        </div>
      )}

      {modalOpen && CmsModal && CmsModalSearch && (
        <CmsModal
          title="Choose video"
          size="lg"
          onClose={() => setModalOpen(false)}
        >
          <CmsModalSearch
            data={{
              formEndpoint: `${window.location.origin}${window.silverstripeContext ? '/' + window.silverstripeContext : ''}/api/bunny/search-form`,
              searchEndpoint: searchEndpoint || `${window.location.origin}${window.silverstripeContext ? '/' + window.silverstripeContext : ''}/api/bunny/search-results`,
              autoSearch: true,
            }}
            onSelect={handleChooseExisting}
          />
        </CmsModal>
      )}
    </div>
  );
};

BunnyVideoUploadField.propTypes = {
  id: PropTypes.string.isRequired,
  name: PropTypes.string.isRequired,
  value: PropTypes.string,
  onChange: PropTypes.func.isRequired,
  data: PropTypes.shape({
    endpoint: PropTypes.string,
    libraryId: PropTypes.string,
    searchEndpoint: PropTypes.string,
    cdnHostname: PropTypes.string,
  }),
  disabled: PropTypes.bool,
  readOnly: PropTypes.bool,
};

BunnyVideoUploadField.defaultProps = {
  value: '',
  data: {},
  disabled: false,
  readOnly: false,
};

export default fieldHolder(BunnyVideoUploadField);
