<?php
/**
 * Profile Image Upload Component
 * Timetable Management System
 * 
 * Reusable component for profile image display and upload
 * Can be included in any profile page
 */

// Prevent direct access
if (!defined('SYSTEM_ACCESS')) {
    die('Direct access denied');
}

// Get current user's profile image
$userId = User::getCurrentUserId();
$db = Database::getInstance();

$profileImageQuery = "SELECT profile_image FROM users WHERE user_id = ?";
$profileImageResult = $db->fetchRow($profileImageQuery, [$userId]);
$currentProfileImage = $profileImageResult['profile_image'] ?? null;

// Generate profile image URL
$profileImageUrl = $currentProfileImage 
    ? UPLOADS_URL . 'profiles/' . $currentProfileImage 
    : ASSETS_URL . 'images/default-avatar.svg';
?>

<div class="profile-image-section">
    <div class="profile-image-container">
        <div class="profile-image-wrapper">
            <img id="profileImagePreview" src="<?= htmlspecialchars($profileImageUrl) ?>" 
                 alt="Profile Image" class="profile-image">
            <div class="profile-image-overlay">
                <i class="fas fa-camera"></i>
                <span>Change Photo</span>
            </div>
        </div>
        
        <div class="profile-image-actions">
            <button type="button" class="btn btn-primary btn-sm" onclick="triggerFileUpload()">
                <i class="fas fa-upload"></i> Upload New Photo
            </button>
            <?php if ($currentProfileImage): ?>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeProfileImage()">
                <i class="fas fa-trash"></i> Remove Photo
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Hidden file input -->
    <input type="file" id="profileImageInput" name="profile_image" 
           accept="image/jpeg,image/jpg,image/png,image/gif" style="display: none;">
    
    <!-- Upload progress -->
    <div id="uploadProgress" class="upload-progress" style="display: none;">
        <div class="progress">
            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
        </div>
        <small class="text-muted">Uploading...</small>
    </div>
    
    <!-- Upload messages -->
    <div id="uploadMessages" class="upload-messages"></div>
</div>

<style>
.profile-image-section {
    margin-bottom: 2rem;
}

.profile-image-container {
    text-align: center;
}

.profile-image-wrapper {
    position: relative;
    display: inline-block;
    margin-bottom: 1rem;
    cursor: pointer;
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.profile-image-wrapper:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.profile-image {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    background: var(--bg-secondary, #f8f9fa);
}

.profile-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    border-radius: 50%;
}

.profile-image-wrapper:hover .profile-image-overlay {
    opacity: 1;
}

.profile-image-overlay i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.profile-image-overlay span {
    font-size: 0.875rem;
    font-weight: 500;
}

.profile-image-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

.upload-progress {
    margin-top: 1rem;
    max-width: 300px;
    margin-left: auto;
    margin-right: auto;
}

.upload-messages {
    margin-top: 1rem;
}

.upload-messages .alert {
    margin-bottom: 0.5rem;
}

/* Dark mode support */
[data-theme="dark"] .profile-image {
    border-color: var(--glass-border);
    background: var(--bg-tertiary);
}

[data-theme="dark"] .profile-image-wrapper {
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

[data-theme="dark"] .profile-image-wrapper:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
}

/* Mobile responsiveness */
@media (max-width: 576px) {
    .profile-image {
        width: 120px;
        height: 120px;
    }
    
    .profile-image-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .profile-image-actions .btn {
        width: 200px;
    }
}
</style>

<script>
function triggerFileUpload() {
    document.getElementById('profileImageInput').click();
}

function removeProfileImage() {
    // Send request to remove profile image (no confirmation prompt)
    fetch('../includes/profile-upload-handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'remove',
            'csrf_token': '<?= $_SESSION[CSRF_TOKEN_NAME] ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update image to default
            const defaultImage = '<?= ASSETS_URL ?>images/default-avatar.svg';
            document.getElementById('profileImagePreview').src = defaultImage;
            showMessage('Profile photo removed successfully', 'success');
            
            // Hide remove button
            const removeBtn = document.querySelector('.btn-outline-danger');
            if (removeBtn) {
                removeBtn.style.display = 'none';
            }

            // Update navbar and any avatar instances immediately
            if (window.updateNavbarProfileImage) {
                window.updateNavbarProfileImage(defaultImage);
            }
            // Update any inline avatar images
            document.querySelectorAll('img.profile-avatar-img, .user-avatar-img, .user-avatar-large-img, #profilePreviewImg').forEach(img => {
                img.src = defaultImage;
            });
        } else {
            showMessage(data.message || 'Failed to remove profile photo', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred while removing the photo', 'error');
    });
}

// Handle file selection
document.getElementById('profileImageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Validate file size (5MB limit)
    const maxSize = <?= MAX_FILE_SIZE ?>;
    if (file.size > maxSize) {
        const maxSizeMB = Math.round(maxSize / (1024 * 1024));
        showMessage(`File is too large. Maximum size is ${maxSizeMB}MB`, 'error');
        return;
    }
    
    // Validate file type
    const allowedTypes = <?= json_encode(ALLOWED_IMAGE_TYPES) ?>;
    const fileExtension = file.name.split('.').pop().toLowerCase();
    if (!allowedTypes.includes(fileExtension)) {
        showMessage(`Invalid file type. Allowed types: ${allowedTypes.join(', ')}`, 'error');
        return;
    }
    
    // Show preview
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('profileImagePreview').src = e.target.result;
    };
    reader.readAsDataURL(file);
    
    // Upload file
    uploadProfileImage(file);
});

function uploadProfileImage(file) {
    const formData = new FormData();
    formData.append('profile_image', file);
    formData.append('csrf_token', '<?= $_SESSION[CSRF_TOKEN_NAME] ?>');
    
    // Show progress
    const progressContainer = document.getElementById('uploadProgress');
    const progressBar = progressContainer.querySelector('.progress-bar');
    progressContainer.style.display = 'block';
    
    // Clear previous messages
    document.getElementById('uploadMessages').innerHTML = '';
    
    fetch('../includes/profile-upload-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        progressContainer.style.display = 'none';
        
        if (data.success) {
            showMessage('Profile photo updated successfully', 'success');

            // Show remove button if it was hidden
            const removeBtn = document.querySelector('.btn-outline-danger');
            if (removeBtn) {
                removeBtn.style.display = 'inline-block';
            }

            // Update navbar avatar and any other instances without full reload
            const newUrl = data.url;
            if (window.updateNavbarProfileImage) {
                window.updateNavbarProfileImage(newUrl);
            }
            // Update any inline avatar images across the page (headers, cards, etc.)
            document.querySelectorAll('img.profile-avatar-img, .user-avatar-img, .user-avatar-large-img, #profilePreviewImg').forEach(img => {
                img.src = newUrl;
            });

            // Also update CSS background-image avatars if any (future-proof)
            document.querySelectorAll('[data-avatar-src]')?.forEach(el => {
                el.style.backgroundImage = `url(${newUrl})`;
            });
        } else {
            showMessage(data.message || 'Failed to upload profile photo', 'error');
        }
    })
    .catch(error => {
        progressContainer.style.display = 'none';
        console.error('Error:', error);
        showMessage('An error occurred while uploading the photo', 'error');
    });
}

function showMessage(message, type) {
    const messagesContainer = document.getElementById('uploadMessages');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="${icon}"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    messagesContainer.innerHTML = alertHtml;
    
    // Auto-hide success messages after 5 seconds
    if (type === 'success') {
        setTimeout(() => {
            const alert = messagesContainer.querySelector('.alert');
            if (alert) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
    }
}

// Handle drag and drop
const imageWrapper = document.querySelector('.profile-image-wrapper');
if (imageWrapper) {
    imageWrapper.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.opacity = '0.7';
    });
    
    imageWrapper.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.opacity = '1';
    });
    
    imageWrapper.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.opacity = '1';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            if (file.type.startsWith('image/')) {
                document.getElementById('profileImageInput').files = files;
                document.getElementById('profileImageInput').dispatchEvent(new Event('change'));
            } else {
                showMessage('Please drop an image file', 'error');
            }
        }
    });
    
    // Click to upload
    imageWrapper.addEventListener('click', triggerFileUpload);
}
</script>
