<?php
$settings = load_system_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid request token.');
        redirect_to('index.php?page=settings');
    }

    $systemName = trim($_POST['system_name'] ?? $settings['system_name']);
    $systemEmail = trim($_POST['system_email'] ?? $settings['system_email']);
    $primaryColor = trim($_POST['primary_color'] ?? '#ff7a00');
    $secondaryColor = trim($_POST['secondary_color'] ?? '#0b1f3a');

    [$uploadOk, $logoPath] = upload_file('logo', 'assets/images');
    if (!$uploadOk) {
        set_flash('danger', $logoPath);
        redirect_to('index.php?page=settings');
    }

    $save = save_system_settings([
        'system_name' => $systemName,
        'system_email' => $systemEmail,
        'primary_color' => $primaryColor,
        'secondary_color' => $secondaryColor,
        'logo' => $logoPath ?: $settings['logo'],
    ]);

    if ($save) {
        log_action('settings_updated', 'settings');
        set_flash('success', 'System settings updated successfully.');
    } else {
        set_flash('danger', 'Failed to save system settings.');
    }

    redirect_to('index.php?page=settings');
}
?>

<div class="card">
    <div class="card-head"><h3>System Settings</h3></div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <label>System Name
                <input type="text" name="system_name" value="<?= e($settings['system_name']); ?>" required>
            </label>
            <label>System Email
                <input type="email" name="system_email" value="<?= e($settings['system_email']); ?>" required>
            </label>
            <label>Primary Color
                <input type="text" name="primary_color" value="<?= e($settings['primary_color']); ?>" placeholder="#ff7a00">
            </label>
            <label>Secondary Color
                <input type="text" name="secondary_color" value="<?= e($settings['secondary_color']); ?>" placeholder="#0b1f3a">
            </label>
            <label class="full">System Logo
                <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg">
            </label>
            <div class="full inline-preview">
                <img src="<?= e(app_url($settings['logo'])); ?>" alt="current logo" class="img-lg-card">
                <span class="muted">Current logo preview</span>
            </div>
            <div class="full">
                <button class="btn btn-primary" type="submit">Save Settings</button>
            </div>
        </form>
    </div>
</div>

