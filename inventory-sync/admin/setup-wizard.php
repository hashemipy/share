<?php
/**
 * صفحه راهنمای تنظیم سایت
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_site_type = Inventory_Sync_Settings::get_current_site_type();
?>

<div class="wrap inventory-sync-setup-wizard">
    <h1>راهنمای تنظیم پلاگین همزمان‌سازی موجودی</h1>
    
    <div class="setup-container" style="max-width: 600px; margin: 40px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        
        <div class="setup-step" style="margin-bottom: 40px;">
            <h2 style="color: #333; margin-bottom: 20px;">مرحله ۱: شناسایی سایت فعلی</h2>
            
            <p style="color: #666; margin-bottom: 20px; line-height: 1.6;">
                لطفاً مشخص کنید که این نصب پلاگین برای کدام سایت است. این انتخاب بسیار مهم است:
            </p>
            
            <form method="post" id="site-type-form">
                <?php wp_nonce_field('inventory_sync_setup'); ?>
                
                <div style="background: #f5f5f5; padding: 20px; border-radius: 5px;">
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: flex; align-items: center; cursor: pointer; font-size: 16px;">
                            <input type="radio" name="site_type" value="1" 
                                   <?php checked($current_site_type, '1'); ?> 
                                   style="width: 20px; height: 20px; cursor: pointer; margin-right: 10px;">
                            <span>
                                <strong>سایت ۱</strong> (سایت اصلی)
                            </span>
                        </label>
                        <p style="margin: 10px 0 0 30px; color: #666; font-size: 13px;">
                            این سایت حاوی اطلاعات اصلی است. تنظیم محصولات در این سایت انجام می‌شود.
                        </p>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: flex; align-items: center; cursor: pointer; font-size: 16px;">
                            <input type="radio" name="site_type" value="2" 
                                   <?php checked($current_site_type, '2'); ?> 
                                   style="width: 20px; height: 20px; cursor: pointer; margin-right: 10px;">
                            <span>
                                <strong>سایت ۲</strong> (سایت هماهنگ‌شده)
                            </span>
                        </label>
                        <p style="margin: 10px 0 0 30px; color: #666; font-size: 13px;">
                            این سایت موجودی را دریافت می‌کند. فقط می‌توانید موجودی را تغییر دهید.
                        </p>
                    </div>
                    
                </div>
                
                <button type="submit" class="button button-primary" style="margin-top: 20px; padding: 10px 30px; font-size: 16px;">
                    ذخیره انتخاب و ادامه
                </button>
            </form>
        </div>
        
        <?php if ($current_site_type): ?>
        <div class="setup-step" style="padding-top: 20px; border-top: 1px solid #eee;">
            <div style="background: #e7f7e7; padding: 15px; border-radius: 5px; margin-top: 20px;">
                <p style="margin: 0; color: #2d662d;">
                    <strong>✓ انتخاب شما: سایت <?php echo esc_html($current_site_type); ?></strong>
                </p>
                <p style="margin: 10px 0 0 0; color: #666; font-size: 13px;">
                    برای تغییر این انتخاب، بالا را انتخاب کنید.
                </p>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
    
</div>

<style>
.inventory-sync-setup-wizard {
    background: #f9f9f9;
    min-height: 100vh;
}

.setup-container {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
jQuery(function($) {
    $('#site-type-form').on('submit', function(e) {
        e.preventDefault();
        
        const siteType = $('input[name="site_type"]:checked').val();
        const nonce = $('input[name="_wpnonce"]').val();
        
        $.post(
            ajaxurl,
            {
                action: 'inventory_sync_set_site_type',
                site_type: siteType,
                nonce: nonce
            },
            function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('خطا: ' + (response.data || 'خطای نامشخص'));
                }
            },
            'json'
        );
    });
});
</script>
