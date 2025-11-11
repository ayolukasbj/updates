<?php
// admin/install-update.php
// Automatic Update Installer (WordPress-style)

require_once 'auth-check.php';
require_once '../config/config.php';
require_once '../config/database.php';

if (!isSuperAdmin()) {
    header('Location: index.php');
    exit;
}

$page_title = 'Install Updates';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';
$update_info = null;
$current_version = defined('SCRIPT_VERSION') ? SCRIPT_VERSION : '1.0';

// Get update info from session or POST
$update_version = $_POST['version'] ?? $_SESSION['update_version'] ?? null;
$update_url = $_POST['download_url'] ?? $_SESSION['update_url'] ?? null;

if ($update_version && $update_url) {
    $_SESSION['update_version'] = $update_version;
    $_SESSION['update_url'] = $update_url;
    $update_info = [
        'version' => $update_version,
        'download_url' => $update_url
    ];
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Install Updates</h1>
    <p>Automatic update installation system</p>
</div>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Current Version</h2>
    </div>
    <div class="card-body">
        <p style="font-size: 18px; margin: 0;">
            <strong>Installed Version:</strong> 
            <span style="color: #3b82f6; font-weight: 600;"><?php echo htmlspecialchars($current_version); ?></span>
        </p>
    </div>
</div>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Sync Files to Updates Folder</h2>
    </div>
    <div class="card-body">
        <p style="margin-bottom: 20px; color: #666;">
            Copy all updated files to the updates folder: <code>C:\Users\HYLINK\Desktop\music - Copy\updates</code>
            <br><small>This will sync all platform files (excluding config, uploads, backups, etc.) to the updates folder.</small>
        </p>
        <div id="sync-progress" style="display: none;">
            <div style="background: #f3f4f6; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <div id="sync-step" style="margin-bottom: 10px; font-weight: 600;">Syncing files...</div>
                <div style="background: #e5e7eb; height: 20px; border-radius: 10px; overflow: hidden;">
                    <div id="sync-bar" style="background: #10b981; height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: 600;">
                        Processing...
                    </div>
                </div>
            </div>
            <div id="sync-log" style="background: #1f2937; color: #10b981; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto; margin-top: 15px;">
                <div>Starting sync...</div>
            </div>
        </div>
        <button id="sync-all-btn" class="btn btn-success" style="padding: 12px 30px;">
            <i class="fas fa-sync"></i> Sync All Files to Updates Folder
        </button>
        <div id="sync-result" style="margin-top: 15px;"></div>
    </div>
</div>

<?php if ($update_info): ?>
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Update Available: Version <?php echo htmlspecialchars($update_info['version']); ?></h2>
    </div>
    <div class="card-body">
        <div id="update-installer">
            <div id="update-progress" style="display: none;">
                <h3>Installing Update...</h3>
                <div style="background: #f3f4f6; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <div id="progress-step" style="margin-bottom: 10px; font-weight: 600;">Preparing...</div>
                    <div style="background: #e5e7eb; height: 20px; border-radius: 10px; overflow: hidden;">
                        <div id="progress-bar" style="background: #3b82f6; height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: 600;">
                            0%
                        </div>
                    </div>
                </div>
                <div id="progress-log" style="background: #1f2937; color: #10b981; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; margin-top: 15px;">
                    <div>Starting update process...</div>
                </div>
            </div>
            
            <div id="update-confirm" style="text-align: center; padding: 40px;">
                <h3 style="margin-bottom: 20px;">Ready to Install Update</h3>
                <p style="margin-bottom: 30px; color: #666;">
                    Update to version <strong><?php echo htmlspecialchars($update_info['version']); ?></strong> will be installed automatically.
                    <br>A backup will be created before installation.
                </p>
                <button id="start-update" class="btn btn-primary" style="padding: 15px 40px; font-size: 18px;">
                    <i class="fas fa-download"></i> Install Update Now
                </button>
                <a href="check-updates.php" class="btn btn-secondary" style="margin-left: 10px;">
                    Cancel
                </a>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="text-align: center; padding: 40px;">
        <p style="color: #666; margin-bottom: 20px;">No update selected for installation.</p>
        <a href="check-updates.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Check for Updates
        </a>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sync all files functionality
    const syncBtn = document.getElementById('sync-all-btn');
    if (syncBtn) {
        syncBtn.addEventListener('click', function() {
            if (!confirm('This will copy all platform files to the updates folder. Continue?')) {
                return;
            }
            
            syncBtn.disabled = true;
            document.getElementById('sync-progress').style.display = 'block';
            const syncStep = document.getElementById('sync-step');
            const syncBar = document.getElementById('sync-bar');
            const syncLog = document.getElementById('sync-log');
            const syncResult = document.getElementById('sync-result');
            
            function addLog(message) {
                const logEntry = document.createElement('div');
                logEntry.textContent = '[' + new Date().toLocaleTimeString() + '] ' + message;
                syncLog.appendChild(logEntry);
                syncLog.scrollTop = syncLog.scrollHeight;
            }
            
            syncStep.textContent = 'Syncing files to updates folder...';
            syncBar.style.width = '50%';
            addLog('Starting file sync...');
            
            fetch('api/install-update.php?action=sync-all', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                syncBar.style.width = '100%';
                if (data.success) {
                    syncStep.textContent = 'Sync Complete!';
                    addLog('Sync completed successfully!');
                    addLog('Files copied: ' + data.copied);
                    addLog('Files skipped: ' + data.skipped);
                    if (data.duration_seconds) {
                        addLog('Duration: ' + data.duration_seconds + ' seconds');
                    }
                    syncResult.innerHTML = '<div class="alert alert-success" style="margin-top: 15px;"><i class="fas fa-check-circle"></i> Sync completed! ' + data.copied + ' files copied, ' + data.skipped + ' files skipped.</div>';
                } else {
                    syncStep.textContent = 'Sync Failed';
                    addLog('ERROR: ' + (data.error || 'Unknown error'));
                    syncResult.innerHTML = '<div class="alert alert-danger" style="margin-top: 15px;"><i class="fas fa-exclamation-circle"></i> Sync failed: ' + (data.error || 'Unknown error') + '</div>';
                }
                syncBtn.disabled = false;
            })
            .catch(error => {
                syncStep.textContent = 'Sync Error';
                addLog('ERROR: ' + error.message);
                syncResult.innerHTML = '<div class="alert alert-danger" style="margin-top: 15px;"><i class="fas fa-exclamation-circle"></i> Sync error: ' + error.message + '</div>';
                syncBtn.disabled = false;
            });
        });
    }
    
    const startBtn = document.getElementById('start-update');
    if (!startBtn) return;
    
    startBtn.addEventListener('click', function() {
        if (!confirm('Are you sure you want to install this update? A backup will be created first.')) {
            return;
        }
        
        // Show progress
        document.getElementById('update-confirm').style.display = 'none';
        document.getElementById('update-progress').style.display = 'block';
        
        // Start update process
        installUpdate();
    });
    
    function installUpdate() {
        const progressStep = document.getElementById('progress-step');
        const progressBar = document.getElementById('progress-bar');
        const progressLog = document.getElementById('progress-log');
        
        function updateProgress(step, percent, message) {
            progressStep.textContent = step;
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
            if (message) {
                const logEntry = document.createElement('div');
                logEntry.textContent = '[' + new Date().toLocaleTimeString() + '] ' + message;
                progressLog.appendChild(logEntry);
                progressLog.scrollTop = progressLog.scrollHeight;
            }
        }
        
        // Step 1: Create backup
        updateProgress('Step 1: Creating Backup', 10, 'Creating backup of current files...');
        
        // Create abort controller for timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 300000); // 5 minutes timeout
        
        fetch('api/install-update.php?action=backup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                version: '<?php echo htmlspecialchars($update_info['version']); ?>'
            }),
            signal: controller.signal
        })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                // Ask user if they want to continue without backup
                const continueWithoutBackup = confirm('Backup creation failed: ' + (data.error || 'Unknown error') + '\n\nDo you want to continue without backup? (Not recommended)');
                if (!continueWithoutBackup) {
                    throw new Error('Update cancelled by user');
                }
                updateProgress('Step 2: Downloading Update', 30, 'Continuing without backup (not recommended). Downloading update...');
            } else {
                const fileCount = data.files_count || 0;
                const sizeMB = data.backup_size_mb || 0;
                updateProgress('Step 2: Downloading Update', 30, 'Backup created successfully (' + fileCount + ' files, ' + sizeMB + 'MB). Downloading update...');
            }
            
            // Step 2: Download update
            return fetch('api/install-update.php?action=download', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    download_url: '<?php echo htmlspecialchars($update_info['download_url']); ?>',
                    version: '<?php echo htmlspecialchars($update_info['version']); ?>'
                })
            });
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Download failed');
            }
            updateProgress('Step 3: Extracting Files', 50, 'Update downloaded. Extracting files...');
            
            // Step 3: Extract files
            return fetch('api/install-update.php?action=extract', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    version: '<?php echo htmlspecialchars($update_info['version']); ?>'
                })
            });
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                }
            });
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Extraction failed');
            }
            updateProgress('Step 4: Installing Files', 70, 'Files extracted. Installing update...');
            
            // Step 4: Install files
            return fetch('api/install-update.php?action=install', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    version: '<?php echo htmlspecialchars($update_info['version']); ?>'
                })
            });
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                }
            });
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Installation failed');
            }
            updateProgress('Step 5: Finalizing', 90, 'Installation complete. Finalizing...');
            
            // Step 5: Finalize
            return fetch('api/install-update.php?action=finalize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    version: '<?php echo htmlspecialchars($update_info['version']); ?>'
                })
            });
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Finalization failed');
            }
            updateProgress('Complete!', 100, 'Update installed successfully!');
            
            // Show success message
            setTimeout(function() {
                alert('Update installed successfully! The page will reload.');
                window.location.href = 'check-updates.php?updated=1';
            }, 2000);
        })
        .catch(error => {
            clearTimeout(timeoutId);
            if (error.name === 'AbortError') {
                updateProgress('Error', 0, 'ERROR: Backup operation timed out after 5 minutes. The backup may still be processing in the background.');
                progressBar.style.background = '#ef4444';
            } else {
                updateProgress('Error', 0, 'ERROR: ' + error.message);
                progressBar.style.background = '#ef4444';
            }
            
            // Show error and rollback option
            setTimeout(function() {
                if (confirm('Update failed. Would you like to restore from backup?')) {
                    fetch('api/install-update.php?action=rollback', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Backup restored successfully.');
                            window.location.reload();
                        } else {
                            alert('Restore failed: ' + (data.error || 'Unknown error'));
                        }
                    });
                }
            }, 1000);
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>


