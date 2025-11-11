// assets/js/upload.js
// Upload page functionality

document.addEventListener('DOMContentLoaded', function() {
    const fileUploadArea = document.getElementById('file-upload-area');
    const fileInput = document.getElementById('audio_file');
    const fileInfo = document.getElementById('file-info');
    const fileName = document.getElementById('file-name');
    const fileSize = document.getElementById('file-size');
    const removeFileBtn = document.getElementById('remove-file');
    const uploadForm = document.getElementById('upload-form');
    const uploadBtn = document.getElementById('upload-btn');
    const fileUploadContent = fileUploadArea.querySelector('.file-upload-content');

    // File upload area click handler
    fileUploadArea.addEventListener('click', function(e) {
        if (e.target !== fileInput) {
            fileInput.click();
        }
    });

    // Drag and drop handlers
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        fileUploadArea.classList.add('dragover');
    });

    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        fileUploadArea.classList.remove('dragover');
    });

    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        fileUploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    });

    // File input change handler
    fileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });

    // Remove file handler
    removeFileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        resetFileUpload();
    });

    // Form submission handler
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!fileInput.files.length) {
            showAlert('Please select an audio file to upload.', 'danger');
            return;
        }

        uploadFile();
    });

    function handleFileSelect(file) {
        // Validate file type
        const allowedTypes = ['audio/mpeg', 'audio/wav', 'audio/flac', 'audio/aac', 'audio/mp4'];
        if (!allowedTypes.includes(file.type) && !isValidAudioExtension(file.name)) {
            showAlert('Please select a valid audio file (MP3, WAV, FLAC, AAC, M4A).', 'danger');
            return;
        }

        // Validate file size
        const maxSize = 50 * 1024 * 1024; // 50MB
        if (file.size > maxSize) {
            showAlert('File size must be less than 50MB.', 'danger');
            return;
        }

        // Show file info
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        
        fileUploadContent.style.display = 'none';
        fileInfo.style.display = 'flex';

        // Show audio preview if possible
        showAudioPreview(file);
    }

    function resetFileUpload() {
        fileInput.value = '';
        fileUploadContent.style.display = 'block';
        fileInfo.style.display = 'none';
        
        // Remove audio preview if exists
        const existingPreview = document.querySelector('.file-preview');
        if (existingPreview) {
            existingPreview.remove();
        }
    }

    function showAudioPreview(file) {
        // Remove existing preview
        const existingPreview = document.querySelector('.file-preview');
        if (existingPreview) {
            existingPreview.remove();
        }

        // Create audio preview
        const preview = document.createElement('div');
        preview.className = 'file-preview';
        preview.innerHTML = `
            <h6><i class="fas fa-music"></i> Audio Preview</h6>
            <audio controls>
                <source src="${URL.createObjectURL(file)}" type="${file.type}">
                Your browser does not support the audio element.
            </audio>
        `;

        fileInfo.appendChild(preview);
    }

    function uploadFile() {
        const formData = new FormData(uploadForm);
        
        // Show loading state
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        uploadForm.classList.add('uploading');

        // Show progress bar
        showProgressBar();

        // Upload file
        fetch('api/upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Song uploaded successfully!', 'success');
                uploadForm.reset();
                resetFileUpload();
                hideProgressBar();
            } else {
                showAlert(data.error || 'Upload failed. Please try again.', 'danger');
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            showAlert('Upload failed. Please check your connection and try again.', 'danger');
        })
        .finally(() => {
            // Reset button state
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Song';
            uploadForm.classList.remove('uploading');
            hideProgressBar();
        });
    }

    function showProgressBar() {
        let progressBar = document.querySelector('.upload-progress');
        if (!progressBar) {
            progressBar = document.createElement('div');
            progressBar.className = 'upload-progress';
            progressBar.innerHTML = `
                <div class="progress">
                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                </div>
                <small class="text-muted mt-2 d-block text-center">Uploading...</small>
            `;
            uploadForm.appendChild(progressBar);
        }
        progressBar.style.display = 'block';

        // Simulate progress
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            
            const progressBarElement = progressBar.querySelector('.progress-bar');
            progressBarElement.style.width = progress + '%';
            
            if (progress >= 90) {
                clearInterval(interval);
            }
        }, 200);
    }

    function hideProgressBar() {
        const progressBar = document.querySelector('.upload-progress');
        if (progressBar) {
            progressBar.style.display = 'none';
        }
    }

    function showAlert(message, type) {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());

        // Create new alert
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        // Insert at the top of the form
        uploadForm.insertBefore(alert, uploadForm.firstChild);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    function isValidAudioExtension(filename) {
        const allowedExtensions = ['.mp3', '.wav', '.flac', '.aac', '.m4a'];
        const extension = filename.toLowerCase().substring(filename.lastIndexOf('.'));
        return allowedExtensions.includes(extension);
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Form validation
    const titleInput = document.getElementById('title');
    const genreSelect = document.getElementById('genre_id');

    titleInput.addEventListener('input', validateForm);
    genreSelect.addEventListener('change', validateForm);

    function validateForm() {
        const isValid = titleInput.value.trim() !== '' && genreSelect.value !== '';
        uploadBtn.disabled = !isValid || !fileInput.files.length;
    }

    // Initialize form validation
    validateForm();
});
