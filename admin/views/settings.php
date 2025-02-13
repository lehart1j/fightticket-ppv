<div class="wrap">
    <h1>FT PPV Settings</h1>
    
    <div class="notice notice-error" style="display:none;" id="ft-ppv-error"></div>
    <div class="notice notice-success" style="display:none;" id="ft-ppv-notice"></div>

    <?php if ($account_id && $api_token): ?>
    <div class="notice notice-info">
        <p>API credentials are currently saved and encrypted.</p>
    </div>
    <?php endif; ?>

    <div class="card">
        <form id="ft-ppv-settings" method="post">
            <table class="form-table">
                <tr>
                    <th><label for="account_id">Cloudflare Account ID</label></th>
                    <td>
                        <input type="text" id="account_id" name="account_id" class="regular-text" 
                            value="<?php echo esc_attr($account_id ? $this->encryption->decrypt($account_id) : ''); ?>" required>
                        <p class="description">Your Cloudflare Account ID</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="api_token">API Token</label></th>
                    <td>
                        <input type="password" id="api_token" name="api_token" class="regular-text" 
                            value="<?php echo esc_attr($api_token ? $this->encryption->decrypt($api_token) : ''); ?>" required>
                        <p class="description">Your Cloudflare API Token with Stream permissions</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Save Settings</button>
            </p>
        </form>
    </div>
</div> 