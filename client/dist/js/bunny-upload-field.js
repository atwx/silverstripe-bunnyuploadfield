/**
 * Bunny Video Upload Field
 * Handles direct browser-to-CDN uploads
 */

class BunnyVideoUploadField {
    constructor(container) {
        this.container = container;
        this.fileInput = container.querySelector('.bunny-file-input');
        this.hiddenInput = container.querySelector('.bunny-video-id-input');
        this.progressContainer = container.querySelector('.upload-progress');
        this.progressFill = container.querySelector('.progress-fill');
        this.progressText = container.querySelector('.progress-text');
        this.statusDiv = container.querySelector('.upload-status');
        this.removeBtn = container.querySelector('.remove-video');

        this.endpoint = container.dataset.endpoint;
        this.libraryId = container.dataset.libraryId;

        this.init();
    }

    init() {
        if (this.fileInput) {
            this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
        }

        if (this.removeBtn) {
            this.removeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.removeVideo();
            });
        }
    }

    async handleFileSelect(event) {
        const file = event.target.files[0];
        if (!file) return;

        // Validate file type
        if (!file.type.startsWith('video/')) {
            this.setStatus('Bitte wählen Sie eine Video-Datei', 'error');
            return;
        }

        // Validate file size (optional, e.g., 5GB limit)
        const maxSize = 5 * 1024 * 1024 * 1024; // 5GB
        if (file.size > maxSize) {
            this.setStatus('Video ist zu groß (max. 5GB)', 'error');
            return;
        }

        this.showProgress();
        this.setStatus('Vorbereitung...', 'info');

        try {
            // Step 1: Create video entry in Bunny
            const createResponse = await this.createVideo(file.name);

            if (!createResponse.ok) {
                const error = await createResponse.json();
                throw new Error(error.error || 'Fehler beim Erstellen des Videos');
            }

            const { videoId, uploadUrl, apiKey } = await createResponse.json();

            // Step 2: Upload file directly to Bunny
            this.setStatus('Upload läuft...', 'info');

            await this.uploadFile(uploadUrl, file, apiKey);

            // Step 3: Update hidden field and reload
            this.hiddenInput.value = videoId;
            this.setStatus('Upload erfolgreich! Video wird verarbeitet...', 'success');
            this.hideProgress();

            // Reload page to show preview after 2 seconds
            // setTimeout(() => {
            //     window.location.reload();
            // }, 2000);

        } catch (error) {
            console.error('Upload error:', error);
            this.setStatus('Fehler: ' + error.message, 'error');
            this.hideProgress();
        }
    }

    async createVideo(filename) {
        const csrfToken = this.getCsrfToken();

        return fetch(this.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                title: filename,
                libraryId: this.libraryId
            })
        });
    }

    uploadFile(url, file, apiKey) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();

            // Track upload progress
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    this.updateProgress(percentComplete);
                }
            });

            xhr.addEventListener('load', () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve({ ok: true });
                } else {
                    reject(new Error('Upload failed with status ' + xhr.status));
                }
            });

            xhr.addEventListener('error', () => {
                reject(new Error('Network error during upload'));
            });

            xhr.addEventListener('abort', () => {
                reject(new Error('Upload was cancelled'));
            });

            xhr.open('PUT', url);
            xhr.setRequestHeader('Content-Type', 'application/octet-stream');
            xhr.setRequestHeader('AccessKey', apiKey);
            xhr.send(file);
        });
    }

    updateProgress(percent) {
        const rounded = Math.round(percent);
        this.progressFill.style.width = rounded + '%';
        this.progressText.textContent = rounded + '%';
    }

    showProgress() {
        if (this.progressContainer) {
            this.progressContainer.style.display = 'block';
        }
    }

    hideProgress() {
        if (this.progressContainer) {
            setTimeout(() => {
                this.progressContainer.style.display = 'none';
                this.progressFill.style.width = '0%';
                this.progressText.textContent = '0%';
            }, 1000);
        }
    }

    setStatus(message, type) {
        if (this.statusDiv) {
            this.statusDiv.textContent = message;
            this.statusDiv.className = 'upload-status upload-status--' + type;
            this.statusDiv.style.display = 'block';
        }
    }

    removeVideo() {
        if (confirm('Video wirklich entfernen?')) {
            this.hiddenInput.value = '';

            // Submit form or reload
            const form = this.container.closest('form');
            if (form) {
                form.submit();
            } else {
                window.location.reload();
            }
        }
    }

    getCsrfToken() {
        // Try to get CSRF token from meta tag
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (tokenMeta) {
            return tokenMeta.getAttribute('content');
        }

        // Try to get from SecurityID field
        const securityField = document.querySelector('input[name="SecurityID"]');
        if (securityField) {
            return securityField.value;
        }

        return '';
    }
}

// Auto-initialize all fields when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFields);
} else {
    initializeFields();
}

function initializeFields() {
    document.querySelectorAll('.bunny-video-upload-field').forEach(container => {
        new BunnyVideoUploadField(container);
    });
}

// Export for use in other scripts if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BunnyVideoUploadField;
}
