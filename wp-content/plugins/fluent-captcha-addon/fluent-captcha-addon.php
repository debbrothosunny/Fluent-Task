<?php
/**
 * Plugin Name: FluentCart Invisible CAPTCHA Pro
 * Version: 4.6
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// ১. প্লাগইন অ্যাক্টিভেশন হুক (এটি সবার আগে বা শেষে রাখতে পারেন)
register_activation_hook( __FILE__, 'fcc_plugin_activation' );

function fcc_plugin_activation() {
    sc_create_log_tables();
}

// ২. ডাটাবেজ টেবিল তৈরি ও আপডেট লজিক
function sc_create_log_tables() {
    global $wpdb;
    $table_logs = $wpdb->prefix . 'fcc_logs';
    $table_blacklist = $wpdb->prefix . 'fcc_blacklist'; // variable defined before use
    $charset_collate = $wpdb->get_charset_collate();
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Logs টেবিল: browser_info কলামটি এখানেই ইনক্লুড করা হয়েছে
    $sql_logs = "CREATE TABLE $table_logs (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        ip_address varchar(100) NOT NULL,
        browser_info varchar(255) DEFAULT '' NOT NULL,
        country_code varchar(10) DEFAULT '' NOT NULL, 
        score decimal(3,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Blacklist টেবিল
    $sql_blacklist = "CREATE TABLE $table_blacklist (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        ip_address varchar(100) NOT NULL,
        blocked_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY ip_address (ip_address)
    ) $charset_collate;";

    // dbDelta স্মার্টলি চেক করবে কলাম আছে কি না, থাকলে কিছু করবে না
    dbDelta($sql_logs);
    dbDelta($sql_blacklist);
}

// ৩. কান্ট্রি ডাটা লজিক (Array format for Map stability)
function sc_get_country_by_ip($ip, $return_only_code = false) {
    // localhost থেকে টেস্ট করলে র্যান্ডম VPN/Proxy IP ব্যবহার করো
    if ($ip === '127.0.0.1' || $ip === '::1') {
        $test_vpn_ips = [
            // বাংলাদেশ (BD)
            '103.230.63.65',     // High proxy/VPN
            '103.245.96.165',    // Hosting detected
            '115.127.119.252',   // Often VPN
            '103.138.123.242',   // SOCKS5 proxy
            '103.118.85.144',    // VPN-like
            '103.251.232.40',    // Hosting/VPN

            // ভারত (IN)
            '103.151.252.34',    // India - Proxy/VPN detected
            '103.194.192.42',    // India - Hosting + proxy
            '45.113.188.74',     // India - High chance VPN
            '103.153.154.85',    // India - Datacenter/VPN

            // রাশিয়া (RU)
            '91.188.239.35',     // Russia - Known proxy
            '185.193.19.12',     // Russia - VPN exit node
            '46.151.149.18',     // Russia - Proxy/VPN
            '185.233.19.218',    // Russia - Datacenter

            // অস্ট্রেলিয়া (AU)
            '103.137.148.66',    // Australia - Hosting/Proxy
            '139.180.128.171',   // Australia - Vultr VPS (hosting)
            '45.121.216.123',    // Australia - Known proxy
            '103.109.100.55'     // Australia - Datacenter/VPN
        ];

        $ip = $test_vpn_ips[array_rand($test_vpn_ips)];
    }

    

    $transient_key = 'fcc_ip_v2_' . md5($ip);
    $data = get_transient($transient_key);

    if (!$data) {
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,proxy,hosting");
        if (!is_wp_error($response)) {
            $api_data = json_decode(wp_remote_retrieve_body($response), true);
            if ($api_data && $api_data['status'] === 'success') {
                $data = [
                    'code' => strtoupper($api_data['countryCode']),
                    'name' => $api_data['country'],
                    'is_vpn' => ($api_data['proxy'] || $api_data['hosting'])
                ];
                set_transient($transient_key, $data, DAY_IN_SECONDS);
            }
        }
    }

    if (!$data) return $return_only_code ? '' : '🏳️ Unknown';

    // যদি শুধু কোড চায় (ম্যাপের জন্য)
    if ($return_only_code) return $data['code'];

    // টেবিলের ডিসপ্লের জন্য HTML
    $flag = '<img src="https://flagcdn.com/16x12/'.strtolower($data['code']).'.png" style="margin-right:8px; vertical-align:middle;">';
    $display = $flag . ' ' . $data['name'];

    if ($data['is_vpn']) {
        $display .= ' <span class="vpn-detected-badge">VPN Detected</span>';
    }

    return $display;
}

// ২. ইউজার এজেন্ট থেকে ব্রাউজার ও ডিভাইস বের করার সিম্পল ফাংশন
function sc_get_browser_details() {
    $ua = $_SERVER['HTTP_USER_AGENT'];
    $browser = "Unknown Browser";
    $platform = "Unknown OS";

    // ১. OS বের করা
    if (preg_match('/linux/i', $ua)) $platform = 'Linux';
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $platform = 'Mac';
    elseif (preg_match('/windows|win32/i', $ua)) $platform = 'Windows';
    elseif (preg_match('/iphone|ipad/i', $ua)) $platform = 'iOS';
    elseif (preg_match('/android/i', $ua)) $platform = 'Android';

    // ২. Browser বের করা (সঠিক অর্ডারে)
    // Edge এবং Opera আগে চেক করতে হয় কারণ এগুলোতে Chrome শব্দটিও থাকে।
    if (preg_match('/Edg/i', $ua)) {
        $browser = 'Edge';
    } elseif (preg_match('/OPR/i', $ua) || preg_match('/Opera/i', $ua)) {
        $browser = 'Opera';
    } elseif (preg_match('/Chrome/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox/i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/MSIE/i', $ua) || preg_match('/Trident/i', $ua)) {
        $browser = 'IE';
    }

    $device = wp_is_mobile() ? "📱 Mobile" : "💻 Desktop";
    
    return "$browser ($platform) - $device";
}


// ২. এডমিন সেটিংস ও মেনু
add_action('admin_menu', function() {
    add_menu_page('FC Captcha', 'FC Captcha', 'manage_options', 'fc-captcha-settings', 'sc_captcha_settings_html', 'dashicons-shield');
    add_submenu_page('fc-captcha-settings', 'Analytics Logs', 'Analytics Dashboard', 'manage_options', 'fc-captcha-logs', 'sc_captcha_logs_html');
});

// Recaptcha সেটিংস পেজ ফাংশন
function sc_captcha_settings_html() { ?>
    <div class="wrap">
        <h1>🛡️ FluentCart reCAPTCHA v3</h1>
        
        <?php 
        // সেটিংস সেভ হয়েছে কি না তা চেক করার জন্য ওয়ার্ডপ্রেসের বিল্ট-ইন মেথড
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            $is_enabled = get_option('sc_enable_captcha');
            ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const isEnabled = <?php echo $is_enabled ? 'true' : 'false'; ?>;
                    
                    Swal.fire({
                        title: 'Settings Saved!',
                        html: isEnabled ? 
                            'Invisible CAPTCHA is <strong>ENABLED</strong>. Your site is protected.' : 
                            'Invisible CAPTCHA is <strong>DISABLED</strong>. Bot protection is off.',
                        icon: isEnabled ? 'success' : 'warning',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 5000, // ৫ সেকেন্ড দেখাবে
                        timerProgressBar: true,
                        background: isEnabled ? '#d4edda' : '#fff3cd',
                        color: isEnabled ? '#155724' : '#856404',
                    });
                });
            </script>
        <?php } ?>

        <form method="post" action="options.php">
            <?php settings_fields('sc_captcha_settings_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enable CAPTCHA</th>
                    <td>
                        <input type="checkbox" name="sc_enable_captcha" value="1" <?php checked(1, get_option('sc_enable_captcha')); ?> />
                       
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Site Key</th>
                    <td><input type="text" name="sc_site_key" value="<?php echo esc_attr(get_option('sc_site_key')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Secret Key</th>
                    <td><input type="password" name="sc_secret_key" value="<?php echo esc_attr(get_option('sc_secret_key')); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button('Save Changes'); ?>
        </form>
    </div>
<?php }






// লগ ড্যাশবোর্ড ফাংশন

function sc_captcha_logs_html() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'fcc_logs';
    $blacklist_table = $wpdb->prefix . 'fcc_blacklist';

    // ১. IP আনব্লক করার লজিক
    if (isset($_GET['unblock_ip']) && check_admin_referer('fcc_unblock_nonce')) {
        $ip_to_unblock = sanitize_text_field($_GET['unblock_ip']);
        $wpdb->delete($blacklist_table, ['ip_address' => $ip_to_unblock]);
        $redirect_url = remove_query_arg(['unblock_ip', '_wpnonce']);
        echo "<script>window.location.href='$redirect_url';</script>";
        exit;
    }

    // ২. IP ব্লক করার লজিক
    if (isset($_GET['block_ip']) && check_admin_referer('fcc_block_nonce')) {
        $ip_to_block = sanitize_text_field($_GET['block_ip']);
        $wpdb->replace($blacklist_table, ['ip_address' => $ip_to_block, 'blocked_at' => current_time('mysql')]);
        $redirect_url = remove_query_arg(['block_ip', '_wpnonce']);
        echo "<script>window.location.href='$redirect_url';</script>";
        exit;
    }

    // ৩. লগ ডিলিট লজিক
    if (isset($_POST['fcc_delete_all']) && check_admin_referer('fcc_delete_action', 'fcc_nonce')) {
        $wpdb->query("TRUNCATE TABLE $table_name");
        echo '<div class="notice notice-success is-dismissible"><p><strong>All security logs cleared successfully!</strong></p></div>';
    }

    // ৪. স্ট্যাটাস ফিল্টার ও সার্চ লজিক
    $search_ip     = isset($_GET['s_ip']) ? sanitize_text_field($_GET['s_ip']) : '';
    $filter_status = isset($_GET['f_status']) ? sanitize_text_field($_GET['f_status']) : 'all';

    $where = " WHERE 1=1";
    if (!empty($search_ip)) {
        $where .= $wpdb->prepare(" AND ip_address LIKE %s", '%' . $wpdb->esc_like($search_ip) . '%');
    }
    if ($filter_status === 'bot') {
        $where .= " AND score < 0.5";
    } elseif ($filter_status === 'human') {
        $where .= " AND score >= 0.5";
    }

    // ৫. পেজিনেশন ও ডাটা ফেচ
    $per_page = 15;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    $total_logs = (int)$wpdb->get_var("SELECT COUNT(id) FROM $table_name $where");
    $total_pages = ceil($total_logs / $per_page);
    $logs = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY id DESC LIMIT $per_page OFFSET $offset");
    $blacklisted_ips = $wpdb->get_col("SELECT ip_address FROM $blacklist_table");

    // গ্রাফ ও ম্যাপের ডাটা (লেটেস্ট ৭ দিন)
    $all_bot_ips = $wpdb->get_col("SELECT ip_address FROM $table_name WHERE score < 0.5");
    $country_counts = [];
    if (!empty($all_bot_ips)) {
        foreach ($all_bot_ips as $ip) {
            $code = sc_get_country_by_ip($ip, true); 
            if ($code) $country_counts[$code] = isset($country_counts[$code]) ? $country_counts[$code] + 1 : 1;
        }
    }

    $labels = []; $bot_data = []; $human_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M d', strtotime($date));
        $bot_data[] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_name WHERE DATE(time) = %s AND score < 0.5", $date));
        $human_data[] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_name WHERE DATE(time) = %s AND score >= 0.5", $date));
    }
    ?>


<!-- Ultra Premium Resources -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap/dist/css/jsvectormap.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/jsvectormap"></script>
<script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    :root {
        --bg: #0f172a;
        --card-bg: rgba(15, 23, 42, 0.7);
        --card-border: rgba(255, 255, 255, 0.1);
        --text: #e2e8f0;
        --text-muted: #94a3b8;
        --primary: #818cf8;
        --danger: #f87171;
        --success: #34d399;
        --warning: #fbbf24;
        --glass: rgba(255, 255, 255, 0.05);
        --shadow: 0 20px 40px rgba(0,0,0,0.3);
    }

    @media (prefers-color-scheme: light) {
        :root {
            --bg: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.9);
            --card-border: rgba(0, 0, 0, 0.1);
            --text: #1e293b;
            --text-muted: #64748b;
            --glass: rgba(255, 255, 255, 0.6);
            --shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
    }

    body {
        background: var(--bg);
        color: var(--text);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .fcc-ultimate-dashboard {
        max-width: 1600px;
        margin: 0 auto;
        padding: 30px;
        min-height: 100vh;
    }

    .fcc-title {
        text-align: center;
        margin-bottom: 40px;
    }

    .fcc-title h1 {
        font-size: 3rem;
        font-weight: 800;
        background: linear-gradient(90deg, #818cf8, #c084fc);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 0;
        letter-spacing: -1px;
    }

    .fcc-title p {
        color: var(--text-muted);
        font-size: 1.2rem;
        margin-top: 10px;
    }

    .fcc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }

    .fcc-metric {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border-radius: 20px;
        padding: 80px;
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow);
        transition: all 0.4s ease;
        position: relative;
        overflow: hidden;
        align-items: center;        /* হরাইজন্টালি সেন্টার */
        text-align: center;         /* টেক্সটও সেন্টার (p এবং h3 এর জন্য) */

    }

    .fcc-metric::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 4px;
        background: linear-gradient(90deg, var(--primary), #c084fc);
        opacity: 0.8;
    }

    .fcc-metric:hover {
        transform: translateY(-12px);
        box-shadow: 0 30px 60px rgba(0,0,0,0.4);
    }

    .fcc-metric-icon {
        font-size: 3rem;
        margin-bottom: 16px;
        opacity: 0.9;
    }

    .fcc-metric h3 {
        font-size: 2.8rem;
        margin: 0 0 8px 0;
        font-weight: 800;
    }

    .fcc-metric p {
        margin: 0;
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.95rem;
        letter-spacing: 1px;
    }

    .chart-wrapper {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 40px;
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow);
    }

    .chart-wrapper h3 {
        margin: 0 0 24px 0;
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text);
    }

    .controls-bar {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border-radius: 20px;
        padding: 24px;
        margin-bottom: 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow);
    }

    .search-group {
        display: flex;
        gap: 12px;
        align-items: center;
        flex: 1;
        min-width: 300px;
    }

    .search-group input, .search-group select {
        padding: 14px 18px;
        border: 1px solid var(--card-border);
        border-radius: 14px;
        background: var(--glass);
        color: var(--text);
        font-size: 1rem;
    }

    .search-group button {
        padding: 14px 30px;
        background: linear-gradient(90deg, #818cf8, #c084fc);
        color: white;
        border: none;
        border-radius: 14px;
        cursor: pointer;
        font-weight: 600;
        font-size: 1rem;
    }

    .table-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow);
        margin-bottom: 40px;
    }

    .table-card h3 {
        background: linear-gradient(90deg, #1e293b, #334155);
        color: white;
        padding: 24px 30px;
        margin: 0;
        font-size: 1.4rem;
        font-weight: 600;
    }

    table.ultra-table {
        width: 100%;
        border-collapse: collapse;
    }

    table.ultra-table th {
        background: rgba(30, 41, 59, 0.6);
        padding: 18px;
        text-align: left;
        font-weight: 600;
        color: var(--text);
        font-size: 0.95rem;
    }

    table.ultra-table td {
        padding: 18px;
        border-bottom: 1px solid var(--card-border);
    }

    table.ultra-table tr:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .score-tag {
        padding: 8px 16px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.9rem;
    }

    .score-safe { background: #064e3b; color: #6ee7b7; }
    .score-danger { background: #7f1d1d; color: #fca5a5; }

    .btn-tiny {
        padding: 6px 12px;
        font-size: 0.8rem;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
    }

    .btn-block-now {
        background: #7f1d1d;
        color: #fca5a5;
        border: 1px solid #991b1b;
    }

    .btn-unblock-premium {
        background: linear-gradient(90deg, #818cf8, #c084fc);
        color: white;
        padding: 12px 24px;
        border-radius: 14px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .pagination-premium {
        padding: 24px;
        text-align: center;
        background: rgba(30, 41, 59, 0.4);
    }

    .blacklist-premium {
        background: linear-gradient(135deg, #7f1d1d, #991b1b);
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 20px 40px rgba(127, 29, 29, 0.4);
        border: 1px solid #991b1b;
    }

    .blacklist-premium h2 {
        color: #fca5a5;
        font-size: 2rem;
        margin-bottom: 12px;
    }

    /* নতুন CSS: Blinking pulse effect যোগ করা */
.jvm-region.bot-active {
    animation: pulse-red 2s infinite;
    cursor: pointer;
}

@keyframes pulse-red {
    0% {
        fill: #f87171;
        fill-opacity: 0.8;
    }
    50% {
        fill: #ef4444;
        fill-opacity: 1;
        filter: drop-shadow(0 0 10px #ef4444);
    }
    100% {
        fill: #f87171;
        fill-opacity: 0.8;
    }
}

.jvm-region.bot-high {
    animation: pulse-danger 1.5s infinite;
}

@keyframes pulse-danger {
    0% { fill: #dc2626; fill-opacity: 0.9; }
    50% { fill: #b91c1c; fill-opacity: 1; filter: drop-shadow(0 0 15px #dc2626); }
    100% { fill: #dc2626; fill-opacity: 0.9; }
}

.vpn-detected-badge {
    background: linear-gradient(135deg, #7f1d1d, #991b1b);
    color: #fca5a5;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-left: 10px;
    box-shadow: 0 4px 12px rgba(127, 29, 29, 0.5);
    display: inline-block;
    border: 1px solid #dc2626;
    animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
    0% { box-shadow: 0 4px 12px rgba(127, 29, 29, 0.5); }
    50% { box-shadow: 0 6px 20px rgba(220, 38, 38, 0.8); }
    100% { box-shadow: 0 4px 12px rgba(127, 29, 29, 0.5); }
}
</style>

<div class="fcc-ultimate-dashboard">

    <div class="fcc-title">
        <h1><i class="fas fa-shield-virus fa-beat-fade"></i> Threat Intelligence Center</h1>
        <p>Advanced real-time bot detection & security analytics dashboard</p>
    </div>

    <!-- Metrics -->
    
    <div class="fcc-grid">
        <div class="fcc-metric">
            <div class="fcc-metric-icon" style="color:#818cf8;margin-bottom: 32px !important;"><i class="fas fa-fingerprint"></i></div>
            <h3 style="color: #ffffff !important; margin: 0 0 8px 0; font-weight: 800; font-size: 2.8rem;">
                <?php echo (int)$wpdb->get_var("SELECT COUNT(id) FROM $table_name"); ?>
            </h3>
            <p style="margin-top: 16px !important;">Total Scans</p>
        </div>
        <div class="fcc-metric">
            <div class="fcc-metric-icon" style="color:#f87171;margin-bottom: 32px !important;"><i class="fas fa-robot"></i></div>
            <h3 style="color: #ffffff !important; margin: 0 0 8px 0; font-weight: 800; font-size: 2.8rem;">
                <?php echo (int)$wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE score < 0.5"); ?>
            </h3>
            <p style="margin-top: 16px !important;">Bots Blocked</p>
        </div>
        <div class="fcc-metric">
            <div class="fcc-metric-icon" style="color:#34d399;margin-bottom: 32px !important;"><i class="fas fa-user-shield"></i></div>
            <h3 style="color: #ffffff !important; margin: 0 0 8px 0; font-weight: 800; font-size: 2.8rem;">
                <?php echo (int)$wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE score >= 0.5"); ?>
            </h3>
            <p style="margin-top: 16px !important;">Verified Humans</p>
        </div>
        <div class="fcc-metric">
            <div class="fcc-metric-icon" style="color:#fbbf24;margin-bottom: 32px !important;"><i class="fas fa-ban"></i></div>
            <h3 style="color: #ffffff !important; margin: 0 0 8px 0; font-weight: 800; font-size: 2.8rem;">
                <?php echo count($blacklisted_ips); ?>
            </h3>
            <p style="margin-top: 16px !important;">Blacklisted IPs</p>
        </div>
    </div>

    <!-- Map & Chart -->
    <div class="chart-wrapper">
        <h3><i class="fas fa-globe-americas"></i> Global Threat Landscape (Real-time Bot Activity)</h3>
        <div id="world-map" style="width:100%; height:500px;"></div>
        <div style="text-align:center; margin-top:16px; color:var(--text-muted); font-size:0.9rem;">
            <span style="display:inline-block; width:12px; height:12px; background:#f87171; border-radius:50%; animation:pulse-red 2s infinite; margin-right:8px;"></span>
            Pulsing = Active bot attacks detected
        </div>
    </div>

    <div class="chart-wrapper">
        <h3><i class="fas fa-chart-area"></i> Activity Overview (Last 7 Days)</h3>
        <canvas id="securityChart" height="130"></canvas>
    </div>

    <!-- Controls -->
    <div class="controls-bar">
        <form method="get" class="search-group">
            <input type="hidden" name="page" value="fc-captcha-logs">
            <input type="text" name="s_ip" placeholder="🔍 Search IP Address..." value="<?php echo esc_attr($search_ip); ?>">
            <select name="f_status">
                <option value="all" <?php selected($filter_status, 'all'); ?>>All Activity</option>
                <option value="bot" <?php selected($filter_status, 'bot'); ?>>Bots Only</option>
                <option value="human" <?php selected($filter_status, 'human'); ?>>Humans Only</option>
            </select>
            <button type="submit">Filter</button>
        </form>

        <form method="post" onsubmit="return confirm('Permanently delete all logs? This cannot be undone.');">
            <?php wp_nonce_field('fcc_delete_action', 'fcc_nonce'); ?>
            <button type="submit" name="fcc_delete_all" style="background:#f87171; color:white; padding:14px 28px; border:none; border-radius:14px; font-weight:600;">
                <i class="fas fa-trash-restore-alt"></i> Clear All Logs
            </button>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="table-card">
        <h3><i class="fas fa-history"></i> Recent Security Events</h3>
        <table class="ultra-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>IP Address</th>
                    <th>Location</th>
                    <th>Client Behavior</th>
                    <th>Risk Score</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs): foreach ($logs as $log): 
                    $is_human = ($log->score >= 0.5); 
                    $is_blacklisted = in_array($log->ip_address, $blacklisted_ips); 
                ?>
                <tr>
                    <td><?php echo date('M d, Y - h:i A', strtotime($log->time)); ?></td>
                    <td>
                        <code><?php echo esc_html($log->ip_address); ?></code>
                        <?php if (!$is_blacklisted): ?>
                            <a href="<?php echo wp_nonce_url(add_query_arg(['block_ip' => $log->ip_address]), 'fcc_block_nonce'); ?>" class="btn-tiny btn-block-now">Block</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $location_display = sc_get_country_by_ip($log->ip_address);
                        
                        // যদি [VPN] ট্যাগ থাকে, তাহলে সুন্দর লাল ব্যাজে পরিবর্তন করো
                        if (strpos($location_display, '[VPN]') !== false) {
                            $location_display = str_replace(
                                '[VPN]',
                                '<span class="vpn-detected-badge">VPN Detected</span>',
                                $location_display
                            );
                        }
                        
                        echo $location_display;
                        ?>
                    </td>
                    <td style="color:var(--text-muted); font-size:0.9rem;"><?php echo esc_html($log->browser_info); ?></td>
                    <td><span class="score-tag <?php echo $is_human ? 'score-safe' : 'score-danger'; ?>"><?php echo number_format($log->score, 2); ?></span></td>
                    <td><strong><?php echo $is_human ? '<span style="color:#34d399">✓ SAFE</span>' : '<span style="color:#f87171">✗ BLOCKED</span>'; ?></strong></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align:center; padding:60px; color:var(--text-muted);">No events found for the selected filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="pagination-premium">
            <?php echo paginate_links(['total' => $total_pages, 'current' => $current_page, 'add_args' => ['s_ip' => $search_ip, 'f_status' => $filter_status]]); ?>
        </div>
    </div>

    <!-- Blacklist -->
    <div class="blacklist-premium">
        <h2><i class="fas fa-user-lock"></i> Permanent Blacklist Zone</h2>
        <p style="color:#fca5a5; margin-bottom:30px; font-size:1.1rem;">These IPs are completely banned from all submission endpoints.</p>

        <table class="ultra-table">
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Blocked On</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wpdb->get_results("SELECT * FROM $blacklist_table ORDER BY blocked_at DESC") as $blocked): ?>
                <tr>
                    <td><code><?php echo esc_html($blocked->ip_address); ?></code></td>
                    <td><?php echo date('M d, Y - h:i A', strtotime($blocked->blocked_at)); ?></td>
                    <td><span style="color:#fca5a5; font-weight:bold;">🔴 IRREVERSIBLE BAN</span></td>
                    <td>
                        <a href="<?php echo wp_nonce_url(add_query_arg('unblock_ip', $blocked->ip_address), 'fcc_unblock_nonce'); ?>" 
                           class="btn-unblock-premium"
                           onclick="return confirm('Remove permanent ban from this IP?');">
                           <i class="fas fa-user-unlock"></i> Unblock
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$wpdb->get_var("SELECT COUNT(*) FROM $blacklist_table")): ?>
                <tr><td colspan="4" style="text-align:center; padding:60px; color:#fca5a5;">No active bans — Your site is currently clean.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {

    // 1. World Map with Blinking Effect (তোমার আগের কোড অক্ষত)
    const map = new jsVectorMap({
        selector: "#world-map",
        map: "world",
        backgroundColor: "transparent",
        regionStyle: {
            initial: {
                fill: "#1e293b",
                fillOpacity: 0.6,
                stroke: "#334155",
                strokeWidth: 0.5
            },
            hover: {
                fill: "#f87171",
                fillOpacity: 1
            }
        },
        visualizeData: {
            scale: ["#fecaca", "#7f1d1d"],
            values: <?php echo json_encode($country_counts ?: []); ?>
        },
        onRegionTooltipShow: (event, tooltip, code) => {
            const count = <?php echo json_encode($country_counts ?: []); ?>[code] || 0;
            if (count > 0) {
                tooltip.text(tooltip.text() + ` — <strong>${count} bot attack${count > 1 ? 's' : ''}</strong>`);
            }
        },
        onLoaded: () => {
            const botData = <?php echo json_encode($country_counts ?: []); ?>;
            Object.keys(botData).forEach(code => {
                const region = document.querySelector(`.jvm-region[data-code="${code.toUpperCase()}"]`);
                if (region) {
                    region.classList.add('bot-active');
                    if (botData[code] >= 5) {
                        region.classList.add('bot-high');
                    }
                }
            });
        }
    });

    // 2. Simple & Lightweight Activity Overview Chart (পুরানো স্টাইল)
    const ctx = document.getElementById('securityChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                    {
                        label: 'Bots',
                        data: <?php echo json_encode($bot_data); ?>,
                        borderColor: '#f87171',
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Humans',
                        data: <?php echo json_encode($human_data); ?>,
                        borderColor: '#34d399',
                        tension: 0.3,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

});
</script>

<?php 
}

// --- ৩. Frontend Script (Fixed Text Only: Suspicious activity detected.) ---
add_action('wp_footer', function() {
    $site_key = get_option('sc_site_key');
    if (!$site_key || !get_option('sc_enable_captcha')) return; ?>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr($site_key); ?>"></script>
    
    <style>
        .fc-modern-toast { border-radius: 12px !important; border-left: 6px solid #28a745 !important; }
        .fc-modern-modal { border-radius: 15px !important; padding: 20px !important; }
        .swal2-icon { transform: scale(0.8); }
        .swal2-title { font-size: 1.5rem !important; }
    </style>
    
<script>
(function() {
    // ১. টোকেন ম্যানেজমেন্ট — ফ্রেশ টোকেন জেনারেট করার ফাংশন
    function getFreshToken() {
        if (typeof grecaptcha !== 'undefined' && grecaptcha.execute) {
            grecaptcha.ready(function() {
                grecaptcha.execute('<?php echo esc_js($site_key); ?>', {action: 'submit'}).then(function(token) {
                    document.querySelectorAll('form').forEach(form => {
                        let input = form.querySelector('input[name="g-recaptcha-response"]');
                        if (!input) {
                            input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'g-recaptcha-response';
                            form.appendChild(input);
                        }
                        input.value = token;
                    });
                });
            });
        }
    }

    // পেজ লোডের পর প্রথমবার টোকেন নেওয়া
    setTimeout(getFreshToken, 1500);

    // প্রতি ৯০ সেকেন্ডে অটো রিফ্রেশ (ব্যাকগ্রাউন্ডে রাখা ভালো)
    setInterval(getFreshToken, 90000);

    // ২. মেসেজ ক্লিনিং ফাংশন
    function getCleanMsg(msg) {
        if (!msg) return 'Suspicious activity detected.';
        if (msg.toLowerCase().includes('blacklisted')) return 'Your IP is blacklisted.';
        return msg.split('(')[0].trim();
    }

    // ৩. SweetAlert ফাংশন — এখানে মূল পরিবর্তন
    function showScAlert(title, text, icon) {
        const isSuccess = icon === 'success';
        const finalMessage = isSuccess ? text : getCleanMsg(text);

        Swal.fire({
            title: title,
            text: finalMessage,
            icon: icon,
            toast: isSuccess,
            position: isSuccess ? 'top-end' : 'center',
            width: isSuccess ? 'auto' : '400px',
            showConfirmButton: !isSuccess,
            confirmButtonText: 'OK',
            confirmButtonColor: '#d33',
            timer: isSuccess ? 4000 : null,
            timerProgressBar: isSuccess,
            background: '#ffffff',
            showClass: {
                popup: `animate__animated ${isSuccess ? 'animate__fadeInRight' : 'animate__zoomIn'}`
            },
            customClass: {
                popup: isSuccess ? 'fc-modern-toast' : 'fc-modern-modal'
            },

            // গুরুত্বপূর্ণ অংশ: এরর অ্যালার্ট বন্ধ হওয়ার সাথে সাথে নতুন টোকেন নেবে
            didClose: () => {
                if (!isSuccess) {
                    // এরর দেখানোর পর ইউজার যদি আবার চেষ্টা করে, তাহলে ফ্রেশ টোকেন থাকবে
                    getFreshToken();
                }
            }
        });
    }

    // ৪. অতিরিক্ত সুরক্ষা: ফর্ম সাবমিটের ঠিক আগেও ফ্রেশ টোকেন নিশ্চিত করা (অপশনাল কিন্তু ভালো)
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            // সাবমিটের আগে জোর করে একবার ফ্রেশ টোকেন নিয়ে নেওয়া
            getFreshToken();
        });
    });

    // ৫. XMLHttpRequest ইন্টারসেপ্টর
    const originalOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function() {
        this.addEventListener('load', function() {
            if (this.responseText) {
                try {
                    const res = JSON.parse(this.responseText);
                    if (this.status >= 400 || (res.errors && res.errors.restricted)) {
                        const rawMsg = res.data?.errors?.restricted?.[0] || res.errors?.restricted?.[0] || '';
                        showScAlert('Access Denied', rawMsg || 'Verification failed.', 'error');
                    } else if (res.success === true || res.status === 'success' || res.data?.status === 'success') {
                        showScAlert('Success', 'Order Placed Successfully!', 'success');
                    }
                } catch (e) {}
            }
        });
        originalOpen.apply(this, arguments);
    };

    // ৬. Fetch ইন্টারসেপ্টর
    const originalFetch = window.fetch;
    window.fetch = async (...args) => {
        const response = await originalFetch(...args);
        const clone = response.clone();
        const contentType = response.headers.get('content-type');

        if (contentType && contentType.includes('application/json')) {
            try {
                const res = await clone.json();
                if (!response.ok || (res.errors && res.errors.restricted)) {
                    const rawMsg = res.data?.errors?.restricted?.[0] || res.errors?.restricted?.[0] || '';
                    showScAlert('Access Denied', rawMsg || 'Verification failed.', 'error');
                } else if (res.success === true || res.status === 'success' || res.data?.status === 'success') {
                    showScAlert('Success', 'Order Placed Successfully!', 'success');
                }
            } catch (e) {}
        }
        return response;
    };

    

})();
</script>
<?php }, 9999);


// ৪. ব্যাকএন্ড ভ্যালিডেশন (ডাটাবেজ লগ এবং অটো-ব্ল্যাকলিস্ট)
add_action('fluentform/before_insert_submission', 'sc_verify_all_forms', 1, 3);
add_filter('fluent_cart/checkout/validate_data', 'sc_verify_all_forms', 1, 2); // FluentCart এর জন্য সঠিক ফিল্টার

function sc_verify_all_forms($errors, $form = null) {
    if (!get_option('sc_enable_captcha')) return $errors;

    global $wpdb;
    $ip = $_SERVER['REMOTE_ADDR'];
    $table_logs = $wpdb->prefix . 'fcc_logs';
    $blacklist_table = $wpdb->prefix . 'fcc_blacklist';
    $user_table = $wpdb->prefix . 'users'; 

    // ১. ব্ল্যাকলিস্ট চেক
    $is_blacklisted = $wpdb->get_var($wpdb->prepare("SELECT id FROM $blacklist_table WHERE ip_address = %s", $ip));
    if ($is_blacklisted) {
        wp_send_json_error([
            'errors' => ['restricted' => ['Security Alert: Your IP is blacklisted.']],
            'status' => 'failed'
        ], 403);
        exit;
    }

    // ২. টোকেন সংগ্রহের লজিক
    $token = '';
    if (!empty($_POST['g-recaptcha-response'])) {
        $token = sanitize_text_field($_POST['g-recaptcha-response']);
    } elseif (!empty($_REQUEST['g-recaptcha-response'])) {
        $token = sanitize_text_field($_REQUEST['g-recaptcha-response']);
    } else {
        $json_data = json_decode(file_get_contents('php://input'), true);
        if (isset($json_data['g-recaptcha-response'])) {
            $token = sanitize_text_field($json_data['g-recaptcha-response']);
        }
    }

    $score = 0.0;
    // ৩. গুগল ভেরিফিকেশন
    if (!empty($token)) {
        $secret_key = get_option('sc_secret_key');
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $secret_key,
                'response' => $token,
                'remoteip' => $ip
            ],
            'sslverify' => false 
        ]);

        if (!is_wp_error($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($result['success']) && $result['success'] == true) {
                $score = isset($result['score']) ? (float)$result['score'] : 0.5;
            }
        }
    }

    // ৪. ডাটাবেজে লগ সেভ করা
    $wpdb->insert($table_logs, [
        'time' => current_time('mysql'),
        'ip_address' => $ip,
        'browser_info' => sc_get_browser_details(),
        'score' => $score
    ]);

    // ৫. অটো-ব্ল্যাকলিস্ট এবং ইমেইল ডিজাইন লজিক
    if ($score < 0.5) {
        $failed_attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_logs WHERE ip_address = %s AND score < 0.5 AND time > DATE_SUB(NOW(), INTERVAL 1 DAY)", $ip
        ));

        if ($failed_attempts >= 3) {
            if ($failed_attempts == 3) {
                $admin_email = $wpdb->get_var("SELECT user_email FROM $user_table WHERE ID = 1");

                if ($admin_email) {
                    $site_name = get_bloginfo('name');
                    $subject = "🚫 Security Alert: IP Blocked on $site_name";
                    $from_email = get_option('admin_email'); 
                    
                    $headers = [
                        'Content-Type: text/html; charset=UTF-8',
                        'From: ' . $site_name . ' <' . $from_email . '>',
                        'Reply-To: ' . $from_email
                    ];

                    // ইমেইলে দেখানোর জন্য অতিরিক্ত ডাটা
                    $browser_info = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Browser';
                    // sc_get_country_code ফাংশনটি আগে ডিফাইন করা থাকতে হবে
                    $country = (function_exists('sc_get_country_code')) ? sc_get_country_code($ip) : 'Local/Unknown';

                    // --- প্রফেশনাল ইমেইল ডিজাইন শুরু ---
                    $message = "
                    <div style='max-width: 600px; margin: 20px auto; font-family: sans-serif; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); text-align: left;'>
                        <div style='background-color: #d32f2f; padding: 20px; text-align: center;'>
                            <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>🚫 Security Alert</h1>
                        </div>
                        <div style='padding: 30px; background-color: #ffffff;'>
                            <h2 style='color: #333; font-size: 20px; margin-top: 0;'>Security Threat Detected</h2>
                            <p style='color: #666; line-height: 1.6;'>
                                আমাদের সিস্টেম আপনার ওয়েবসাইট <strong>$site_name</strong>-এ সন্দেহজনক কার্যক্রম শনাক্ত করেছে। একজন ইউজার বারবার ভেরিফিকেশনে ব্যর্থ হওয়ায় তার আইপি অটোমেটিক ব্ল্যাকলিস্ট করা হয়েছে।
                            </p>
                            
                            <div style='background-color: #f9f9f9; padding: 20px; border-left: 4px solid #d32f2f; margin: 25px 0;'>
                                <p style='margin: 5px 0;'><strong>IP Address:</strong> <span style='color: #d32f2f;'>$ip</span></p>
                                <p style='margin: 5px 0;'><strong>Country:</strong> $country</p>
                                <p style='margin: 5px 0;'><strong>reCAPTCHA Score:</strong> <span style='font-weight: bold;'>$score</span></p>
                                <p style='margin: 8px 0; font-size: 13px; line-height: 1.4;'><strong>Browser Details:</strong><br><span style='color: #777;'>$browser_info</span></p>
                                <p style='margin: 5px 0;'><strong>Failed Attempts:</strong> 3 Times</p>
                                <p style='margin: 5px 0;'><strong>Action Taken:</strong> Automatically Blacklisted</p>
                                <p style='margin: 5px 0;'><strong>Time:</strong> " . current_time('mysql') . "</p>
                            </div>

                            <div style='text-align: center; margin-top: 30px;'>
                                <a href='" . admin_url('admin.php?page=fc-captcha-logs') . "' style='background-color: #333; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>View Security Logs</a>
                            </div>
                        </div>
                        <div style='background-color: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #888;'>
                            &copy; " . date('Y') . " $site_name Security System. All rights reserved.
                        </div>
                    </div>";
                    // --- প্রফেশনাল ইমেইল ডিজাইন শেষ ---

                    // মেইল পাঠানো
                    $mail_sent = wp_mail($admin_email, $subject, $message, $headers);

                    // ডিবাগ লগ আপডেট
                    $log_file = ABSPATH . 'fcc_debug_log.txt';
                    $status = $mail_sent ? "SUCCESS" : "FAILED";
                    $log_entry = "[" . current_time('mysql') . "] Mail $status to: $admin_email | IP: $ip | Score: $score\n";
                    @file_put_contents($log_file, $log_entry, FILE_APPEND);
                }
            }

            // ব্ল্যাকলিস্টে যুক্ত করা
            $wpdb->replace($blacklist_table, [
                'ip_address' => $ip,
                'blocked_at' => current_time('mysql')
            ]);
        }

        wp_send_json_error(['status' => 'failed', 'errors' => ['restricted' => ["Verification failed (Score: $score)."]]], 422);
        exit;
    }

    return $errors;
}

// ২.১ সেটিংস রেজিস্টার করা (Fix for the "allowed options list" error)
add_action('admin_init', 'sc_captcha_register_settings');
function sc_captcha_register_settings() {
    register_setting('sc_captcha_settings_group', 'sc_enable_captcha');
    register_setting('sc_captcha_settings_group', 'sc_site_key');
    register_setting('sc_captcha_settings_group', 'sc_secret_key');
    register_setting('sc_captcha_settings_group', 'fcc_test_mode'); // এটি অ্যাড করা হলো
}