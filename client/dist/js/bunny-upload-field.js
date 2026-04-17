/**
 * BunnyVideoUploadField - SilverStripe React Component
 *
 * Registers with the SilverStripe JavaScript Injector so the CMS admin
 * can render this as a custom schema form field.
 *
 * `Injector` and `React` are globals exposed by silverstripe/admin's bundle.js
 * and vendor.js respectively - no webpack build needed.
 */
(function () {
    'use strict';

    var h = React.createElement;
    var useState = React.useState;

    function BunnyVideoUploadField(props) {
        var data = props.data || {};
        var endpoint = data.endpoint || '';
        var libraryId = data.libraryId || '';

        var _value = useState(props.value || '');
        var videoId = _value[0];
        var setVideoId = _value[1];

        var _status = useState(null);
        var status = _status[0];
        var setStatus = _status[1];

        var _progress = useState(0);
        var progress = _progress[0];
        var setProgress = _progress[1];

        var _uploading = useState(false);
        var uploading = _uploading[0];
        var setUploading = _uploading[1];

        function notifyChange(newValue) {
            setVideoId(newValue);
            if (typeof props.onChange === 'function') {
                props.onChange({ target: { value: newValue, name: props.name } });
            }
        }

        function getCsrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) return meta.getAttribute('content');
            var field = document.querySelector('input[name="SecurityID"]');
            if (field) return field.value;
            return '';
        }

        function handleFileChange(e) {
            var file = e.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('video/')) {
                setStatus({ message: 'Bitte wählen Sie eine Video-Datei', type: 'error' });
                return;
            }

            var maxSize = 5 * 1024 * 1024 * 1024; // 5 GB
            if (file.size > maxSize) {
                setStatus({ message: 'Video ist zu groß (max. 5GB)', type: 'error' });
                return;
            }

            setUploading(true);
            setProgress(0);
            setStatus({ message: 'Vorbereitung...', type: 'info' });

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify({ title: file.name, libraryId: libraryId })
            })
                .then(function (response) {
                    if (!response.ok) {
                        return response.json().then(function (err) {
                            throw new Error(err.error || 'Fehler beim Erstellen des Videos');
                        });
                    }
                    return response.json();
                })
                .then(function (result) {
                    setStatus({ message: 'Upload läuft...', type: 'info' });

                    return new Promise(function (resolve, reject) {
                        var xhr = new XMLHttpRequest();

                        xhr.upload.addEventListener('progress', function (e) {
                            if (e.lengthComputable) {
                                setProgress(Math.round((e.loaded / e.total) * 100));
                            }
                        });

                        xhr.addEventListener('load', function () {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                resolve(result.videoId);
                            } else {
                                reject(new Error('Upload fehlgeschlagen: ' + xhr.status));
                            }
                        });

                        xhr.addEventListener('error', function () {
                            reject(new Error('Netzwerkfehler beim Upload'));
                        });

                        xhr.open('PUT', result.uploadUrl);
                        xhr.setRequestHeader('Content-Type', 'application/octet-stream');
                        xhr.send(file);
                    });
                })
                .then(function (newVideoId) {
                    notifyChange(newVideoId);
                    setStatus({ message: 'Upload erfolgreich! Video wird verarbeitet...', type: 'success' });
                    setUploading(false);
                })
                .catch(function (err) {
                    setStatus({ message: 'Fehler: ' + err.message, type: 'error' });
                    setUploading(false);
                });
        }

        function handleRemove(e) {
            e.preventDefault();
            if (confirm('Video wirklich entfernen?')) {
                notifyChange('');
                setStatus(null);
            }
        }

        var children = [];

        // Status message
        if (status) {
            children.push(h('div', {
                key: 'status',
                className: 'upload-status upload-status--' + status.type
            }, status.message));
        }

        // Existing video or upload input
        if (videoId) {
            children.push(h('div', { key: 'current', className: 'bunny-video-current' },
                h('p', { className: 'bunny-video-id' },
                    h('strong', null, 'Video ID: '), videoId
                ),
                h('button', {
                    type: 'button',
                    className: 'btn btn-danger btn-sm mt-1',
                    onClick: handleRemove
                }, 'Video entfernen')
            ));
        } else {
            children.push(h('div', { key: 'upload', className: 'bunny-upload-area' },
                h('input', {
                    type: 'file',
                    accept: 'video/*',
                    onChange: handleFileChange,
                    disabled: uploading,
                    className: 'form-control bunny-file-input'
                })
            ));
        }

        // Progress bar (Bootstrap-compatible)
        if (uploading) {
            children.push(h('div', { key: 'progress', className: 'upload-progress mt-2' },
                h('div', { className: 'progress' },
                    h('div', {
                        className: 'progress-bar',
                        role: 'progressbar',
                        style: { width: progress + '%' },
                        'aria-valuenow': progress,
                        'aria-valuemin': 0,
                        'aria-valuemax': 100
                    }, progress + '%')
                )
            ));
        }

        return h('div', { className: 'bunny-video-upload-field' }, children);
    }

    // Register with SilverStripe's Injector.
    // `Injector` is a global set by silverstripe/admin's bundle.js before this script runs.
    Injector.component.register('BunnyVideoUploadField', BunnyVideoUploadField);

}());
