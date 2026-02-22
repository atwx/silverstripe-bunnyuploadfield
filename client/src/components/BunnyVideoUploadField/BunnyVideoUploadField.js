import React, { useState } from 'react';
import PropTypes from 'prop-types';
import fieldHolder from 'components/FieldHolder/FieldHolder';

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
        reject(new Error(`Upload fehlgeschlagen (Status ${xhr.status})`));
      }
    });
    xhr.addEventListener('error', () => reject(new Error('Netzwerkfehler beim Upload')));
    xhr.addEventListener('abort', () => reject(new Error('Upload abgebrochen')));
    xhr.open('PUT', url);
    xhr.setRequestHeader('Content-Type', 'application/octet-stream');
    xhr.setRequestHeader('AccessKey', apiKey);
    xhr.send(file);
  });
}

const BunnyVideoUploadField = ({ id, name, value, onChange, data, disabled, readOnly }) => {
  const { endpoint, libraryId } = data || {};
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);
  const [status, setStatus] = useState(null);

  const handleFileSelect = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('video/')) {
      setStatus({ text: 'Bitte wählen Sie eine Video-Datei', type: 'error' });
      return;
    }

    setUploading(true);
    setProgress(0);
    setStatus({ text: 'Vorbereitung...', type: 'info' });

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
        throw new Error(err.error || 'Fehler beim Erstellen des Videos');
      }

      const { videoId, uploadUrl, apiKey } = await createRes.json();

      setStatus({ text: 'Upload läuft...', type: 'info' });
      await uploadFile(uploadUrl, file, apiKey, setProgress);

      onChange(videoId);
      setStatus({ text: 'Upload erfolgreich! Video wird verarbeitet...', type: 'success' });
    } catch (err) {
      setStatus({ text: `Fehler: ${err.message}`, type: 'error' });
    } finally {
      setUploading(false);
    }
  };

  const handleRemove = () => {
    // eslint-disable-next-line no-alert
    if (window.confirm('Video wirklich entfernen?')) {
      onChange('');
      setStatus(null);
    }
  };

  if (readOnly) {
    if (!value) {
      return <span className="bunny-video-upload-field--empty">—</span>;
    }
    return (
      <div className="bunny-video-upload-field bunny-video-upload-field--readonly">
        <iframe
          src={`https://iframe.mediadelivery.net/bunny/${libraryId}/${value}`}
          loading="lazy"
          style={{ border: 0, width: '100%', maxWidth: 640, aspectRatio: '16/9' }}
          allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture"
          allowFullScreen
        />
      </div>
    );
  }

  return (
    <div className="bunny-video-upload-field">
      <input type="hidden" name={name} id={id} value={value || ''} readOnly />

      {!value && (
        <div className="upload-container">
          <input
            type="file"
            id={`${id}_file`}
            accept="video/*"
            className="bunny-file-input"
            onChange={handleFileSelect}
            disabled={uploading || disabled}
          />
          <label htmlFor={`${id}_file`} className="upload-button">
            <span className="icon">📹</span>
            <span className="text">Video auswählen</span>
          </label>
        </div>
      )}

      {uploading && (
        <div className="upload-progress">
          <div className="progress-bar">
            <div className="progress-fill" style={{ width: `${progress}%` }} />
          </div>
          <div className="progress-text">{progress}%</div>
        </div>
      )}

      {status && (
        <div className={`upload-status upload-status--${status.type}`}>
          {status.text}
        </div>
      )}

      {value && !uploading && (
        <div className="current-video">
          <div className="video-info">
            <strong>Video ID:</strong> {value}
          </div>
          <iframe
            src={`https://iframe.mediadelivery.net/embed/${libraryId}/${value}`}
            loading="lazy"
            style={{ border: 0, width: '100%', maxWidth: 640, aspectRatio: '16/9' }}
            allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture"
            allowFullScreen
          />
          <button
            type="button"
            className="remove-video btn btn-danger btn-sm"
            onClick={handleRemove}
          >
            Video entfernen
          </button>
        </div>
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
