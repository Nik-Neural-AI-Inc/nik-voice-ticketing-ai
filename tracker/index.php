<?php
/**
 * Nik VoiceDesk AI - Standalone Telemetry Tracker & Dashboard
 * 
 * Target URL: https://aboozaresmaili.com/tracking/
 * Backed by a local, zero-config SQLite database (tracking.sqlite)
 */

define( 'TRACKER_PASSWORD', 'niktelemetry2026' ); // Change to your desired dashboard password
define( 'DB_FILE', __DIR__ . '/tracking.sqlite' );

if ( session_status() === PHP_SESSION_NONE ) {
    session_start();
}

function get_db() {
    static $db = null;
    if ( $db === null ) {
        try {
            $db = new PDO( 'sqlite:' . DB_FILE );
            $db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            $db->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
            $db->exec( 'PRAGMA journal_mode = WAL;' );
            $db->exec( "
                CREATE TABLE IF NOT EXISTS sites (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    domain TEXT UNIQUE NOT NULL,
                    site_url TEXT NOT NULL,
                    admin_email TEXT,
                    wp_version TEXT,
                    php_version TEXT,
                    plugin_version TEXT,
                    server_software TEXT,
                    plan_status TEXT DEFAULT 'free',
                    ticket_count INTEGER DEFAULT 0,
                    ip_address TEXT,
                    user_agent TEXT,
                    ping_count INTEGER DEFAULT 1,
                    first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_domain ON sites(domain);
                CREATE INDEX IF NOT EXISTS idx_last_seen ON sites(last_seen);
            " );
        } catch ( Exception $e ) {
            http_response_code( 500 );
            die( json_encode( array( 'error' => 'Database error: ' . $e->getMessage() ) ) );
        }
    }
    return $db;
}

// -----------------------------------------------------------------------------
// API Ingestion (POST)
// -----------------------------------------------------------------------------
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Access-Control-Allow-Origin: *' );
    header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
    header( 'Access-Control-Allow-Headers: Content-Type' );

    if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
        exit;
    }

    $raw = file_get_contents( 'php://input' );
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) || empty( $data ) ) {
        $data = $_POST;
    }

    $site_url = filter_var( $data['site_url'] ?? '', FILTER_SANITIZE_URL );
    if ( empty( $site_url ) ) {
        http_response_code( 400 );
        echo json_encode( array( 'success' => false, 'message' => 'Missing site_url' ) );
        exit;
    }

    $host = parse_url( $site_url, PHP_URL_HOST );
    $domain = strtolower( trim( $host ?: $site_url ) );
    if ( strpos( $domain, 'www.' ) === 0 ) {
        $domain = substr( $domain, 4 );
    }

    $admin_email     = filter_var( $data['admin_email'] ?? '', FILTER_SANITIZE_EMAIL );
    $wp_version      = preg_replace( '/[^0-9\.\-]/', '', $data['wp_version'] ?? 'unknown' );
    $php_version     = preg_replace( '/[^0-9\.\-]/', '', $data['php_version'] ?? PHP_VERSION );
    $plugin_version  = preg_replace( '/[^0-9\.\-]/', '', $data['plugin_version'] ?? '1.2.0' );
    $server_software = substr( strip_tags( $data['server_software'] ?? ( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ) ), 0, 80 );
    $plan_status     = strtolower( trim( $data['plan_status'] ?? 'free' ) );
    if ( ! in_array( $plan_status, array( 'free', 'pro', 'enterprise' ), true ) ) {
        $plan_status = 'free';
    }
    $ticket_count    = (int) ( $data['ticket_count'] ?? 0 );
    $ip_address      = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    $ip_address      = explode( ',', $ip_address )[0];
    $user_agent      = substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200 );

    $db = get_db();
    try {
        $stmt = $db->prepare( "
            INSERT INTO sites (domain, site_url, admin_email, wp_version, php_version, plugin_version, server_software, plan_status, ticket_count, ip_address, user_agent, ping_count, first_seen, last_seen)
            VALUES (:domain, :site_url, :admin_email, :wp_version, :php_version, :plugin_version, :server_software, :plan_status, :ticket_count, :ip_address, :user_agent, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(domain) DO UPDATE SET
                site_url = excluded.site_url,
                admin_email = excluded.admin_email,
                wp_version = excluded.wp_version,
                php_version = excluded.php_version,
                plugin_version = excluded.plugin_version,
                server_software = excluded.server_software,
                plan_status = excluded.plan_status,
                ticket_count = excluded.ticket_count,
                ip_address = excluded.ip_address,
                user_agent = excluded.user_agent,
                ping_count = sites.ping_count + 1,
                last_seen = CURRENT_TIMESTAMP
        " );

        $stmt->execute( array(
            ':domain'          => $domain,
            ':site_url'        => $site_url,
            ':admin_email'     => $admin_email,
            ':wp_version'      => $wp_version,
            ':php_version'     => $php_version,
            ':plugin_version'  => $plugin_version,
            ':server_software' => $server_software,
            ':plan_status'     => $plan_status,
            ':ticket_count'    => $ticket_count,
            ':ip_address'      => $ip_address,
            ':user_agent'      => $user_agent,
        ) );

        echo json_encode( array( 'success' => true, 'message' => 'Telemetry saved', 'domain' => $domain ) );
        exit;
    } catch ( Exception $e ) {
        http_response_code( 500 );
        echo json_encode( array( 'success' => false, 'error' => $e->getMessage() ) );
        exit;
    }
}

// -----------------------------------------------------------------------------
// Authentication handling
// -----------------------------------------------------------------------------
if ( isset( $_GET['action'] ) && $_GET['action'] === 'logout' ) {
    unset( $_SESSION['tracker_auth'] );
    header( 'Location: ' . strtok( $_SERVER['REQUEST_URI'], '?' ) );
    exit;
}

if ( isset( $_GET['pass'] ) && $_GET['pass'] === TRACKER_PASSWORD ) {
    $_SESSION['tracker_auth'] = true;
}

$login_error = '';
if ( isset( $_POST['action'] ) && $_POST['action'] === 'login' ) {
    if ( ( $_POST['password'] ?? '' ) === TRACKER_PASSWORD ) {
        $_SESSION['tracker_auth'] = true;
        header( 'Location: ' . $_SERVER['REQUEST_URI'] );
        exit;
    } else {
        $login_error = 'Incorrect password.';
    }
}

$is_auth = ! empty( $_SESSION['tracker_auth'] );

if ( $is_auth && isset( $_GET['export'] ) && $_GET['export'] === 'csv' ) {
    $db = get_db();
    $rows = $db->query( "SELECT domain, site_url, admin_email, plan_status, wp_version, php_version, plugin_version, server_software, ticket_count, ping_count, first_seen, last_seen FROM sites ORDER BY last_seen DESC" )->fetchAll();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=nik-telemetry-' . date( 'Y-m-d' ) . '.csv' );
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'Domain', 'Site URL', 'Admin Email', 'Plan', 'WordPress', 'PHP', 'Plugin', 'Server', 'Tickets', 'Pings', 'First Seen', 'Last Seen' ) );
    foreach ( $rows as $r ) {
        fputcsv( $out, $r );
    }
    fclose( $out );
    exit;
}

if ( $is_auth && isset( $_GET['action'] ) && $_GET['action'] === 'delete' && ! empty( $_GET['id'] ) ) {
    $db = get_db();
    $stmt = $db->prepare( "DELETE FROM sites WHERE id = :id" );
    $stmt->execute( array( ':id' => (int) $_GET['id'] ) );
    header( 'Location: ' . strtok( $_SERVER['REQUEST_URI'], '?' ) );
    exit;
}

function time_ago( $datetime ) {
    $time = strtotime( $datetime );
    $diff = time() - $time;
    if ( $diff < 60 ) return 'Just now';
    if ( $diff < 3600 ) return floor( $diff / 60 ) . 'm ago';
    if ( $diff < 86400 ) return floor( $diff / 3600 ) . 'h ago';
    if ( $diff < 604800 ) return floor( $diff / 86400 ) . 'd ago';
    return date( 'M j, Y', $time );
}

$db = get_db();
$sites = $is_auth ? $db->query( "SELECT * FROM sites ORDER BY last_seen DESC" )->fetchAll() : array();

$total_sites = count( $sites );
$active_sites = 0;
$pro_sites = 0;
$total_pings = 0;
$now = time();

foreach ( $sites as $s ) {
    if ( ( $now - strtotime( $s['last_seen'] ) ) <= 604800 ) {
        $active_sites++;
    }
    if ( in_array( $s['plan_status'], array( 'pro', 'enterprise' ), true ) ) {
        $pro_sites++;
    }
    $total_pings += (int) $s['ping_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nik VoiceDesk AI - Telemetry Intelligence</title>
    <style>
        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --card-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
        }
        body.dark-mode {
            --bg: #090d16;
            --surface: #0f172a;
            --border: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #38bdf8;
            --primary-hover: #0284c7;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            line-height: 1.5;
            transition: background 0.2s ease, color 0.2s ease;
            padding: 24px;
        }
        .container { max-width: 1280px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 24px;
            margin-bottom: 24px;
            box-shadow: var(--card-shadow);
        }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: bold; font-size: 18px;
        }
        .brand-text h1 { font-size: 18px; font-weight: 700; }
        .brand-text p { font-size: 12px; color: var(--text-muted); }
        .header-actions { display: flex; align-items: center; gap: 10px; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; font-size: 13px; font-weight: 600;
            border-radius: 6px; text-decoration: none; cursor: pointer;
            border: 1px solid var(--border); background: var(--surface); color: var(--text-main);
            transition: all 0.15s ease;
        }
        .btn:hover { border-color: var(--primary); color: var(--primary); }
        .btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); color: #fff; }
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px; margin-bottom: 24px;
        }
        .stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 18px 20px; box-shadow: var(--card-shadow);
        }
        .stat-label { font-size: 12.5px; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-val { font-size: 28px; font-weight: 800; color: var(--text-main); margin-top: 4px; }
        .controls-bar {
            display: flex; justify-content: space-between; align-items: center;
            gap: 16px; margin-bottom: 16px; flex-wrap: wrap;
        }
        .search-input {
            flex: 1; max-width: 360px; padding: 9px 14px;
            border: 1px solid var(--border); border-radius: 8px;
            background: var(--surface); color: var(--text-main); font-size: 14px; outline: none;
        }
        .search-input:focus { border-color: var(--primary); }
        .filter-group { display: flex; gap: 8px; }
        .filter-btn {
            padding: 6px 12px; font-size: 12.5px; font-weight: 600;
            border-radius: 6px; border: 1px solid var(--border);
            background: var(--surface); color: var(--text-muted); cursor: pointer;
        }
        .filter-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .table-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; overflow: hidden; box-shadow: var(--card-shadow);
        }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px; }
        th {
            background: rgba(0,0,0,0.02); padding: 12px 16px;
            color: var(--text-muted); font-weight: 600; font-size: 12px;
            text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border);
        }
        body.dark-mode th { background: rgba(255,255,255,0.02); }
        td { padding: 14px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(0,0,0,0.015); }
        body.dark-mode tr:hover td { background: rgba(255,255,255,0.02); }
        .domain-cell { display: flex; flex-direction: column; gap: 2px; }
        .domain-name { font-weight: 700; color: var(--text-main); text-decoration: none; }
        .domain-name:hover { color: var(--primary); }
        .domain-url { font-size: 12px; color: var(--text-muted); }
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 700;
        }
        .badge-free { background: #f1f5f9; color: #475569; }
        body.dark-mode .badge-free { background: #1e293b; color: #94a3b8; }
        .badge-pro { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        body.dark-mode .badge-pro { background: rgba(16,185,129,0.15); color: #34d399; border-color: rgba(16,185,129,0.3); }
        .dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        .dot-green { background: #22c55e; box-shadow: 0 0 6px #22c55e; }
        .dot-gray { background: #94a3b8; }
        .login-card {
            max-width: 400px; margin: 80px auto; background: var(--surface);
            border: 1px solid var(--border); border-radius: 12px;
            padding: 32px; box-shadow: var(--card-shadow); text-align: center;
        }
        .login-card h2 { margin-bottom: 8px; font-size: 20px; }
        .login-card p { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; }
        .login-input {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border);
            border-radius: 6px; font-size: 14px; margin-bottom: 16px;
            background: var(--bg); color: var(--text-main); outline: none;
        }
        .login-input:focus { border-color: var(--primary); }
        .login-btn { width: 100%; justify-content: center; padding: 10px; }
        .error-msg { background: #fee2e2; color: #b91c1c; padding: 8px; border-radius: 6px; font-size: 12.5px; margin-bottom: 14px; }
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; gap: 14px; }
            .header-actions { width: 100%; justify-content: space-between; }
            .controls-bar { flex-direction: column; align-items: stretch; }
            .search-input { max-width: 100%; }
            table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ( ! $is_auth ) : ?>
            <div class="login-card">
                <div class="brand-icon" style="margin: 0 auto 16px; width: 48px; height: 48px; font-size: 22px;">👑</div>
                <h2>Nik VoiceDesk AI</h2>
                <p>Enter administrator password to access the telemetry tracking dashboard.</p>
                <?php if ( $login_error ) : ?>
                    <div class="error-msg"><?php echo htmlspecialchars( $login_error ); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <input type="password" name="password" class="login-input" placeholder="Password" required autofocus>
                    <button type="submit" class="btn btn-primary login-btn">Access Dashboard</button>
                </form>
            </div>
        <?php else : ?>
            <div class="header">
                <div class="brand">
                    <div class="brand-icon">⚡</div>
                    <div class="brand-text">
                        <h1>Nik VoiceDesk AI — Telemetry</h1>
                        <p>Real-time analytics for WordPress installations & active domains</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button type="button" class="btn" id="theme-toggle">🌙 Mode</button>
                    <a href="?export=csv" class="btn">📥 Export CSV</a>
                    <a href="?action=logout" class="btn" style="color:#ef4444;">Logout</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Sites</div>
                    <div class="stat-val"><?php echo number_format( $total_sites ); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active (7 Days)</div>
                    <div class="stat-val" style="color:#16a34a;"><?php echo number_format( $active_sites ); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Enterprise Pro</div>
                    <div class="stat-val" style="color:#7c3aed;"><?php echo number_format( $pro_sites ); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Pings</div>
                    <div class="stat-val" style="color:#0284c7;"><?php echo number_format( $total_pings ); ?></div>
                </div>
            </div>

            <div class="controls-bar">
                <input type="text" id="search-input" class="search-input" placeholder="Search domain, email, server...">
                <div class="filter-group">
                    <button type="button" class="filter-btn active" data-filter="all">All (<?php echo $total_sites; ?>)</button>
                    <button type="button" class="filter-btn" data-filter="active">Active (<?php echo $active_sites; ?>)</button>
                    <button type="button" class="filter-btn" data-filter="pro">Pro (<?php echo $pro_sites; ?>)</button>
                    <button type="button" class="filter-btn" data-filter="free">Free (<?php echo $total_sites - $pro_sites; ?>)</button>
                </div>
            </div>

            <div class="table-card">
                <table id="sites-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Domain</th>
                            <th>Admin Email</th>
                            <th>Plan</th>
                            <th>WP / PHP</th>
                            <th>Plugin</th>
                            <th>Server</th>
                            <th>Pings</th>
                            <th>Last Active</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $sites ) ) : ?>
                            <tr>
                                <td colspan="10" style="text-align:center; padding:36px; color:var(--text-muted);">
                                    No installation records received yet. Telemetry pings will automatically appear here.
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $sites as $s ) : 
                                $is_active = ( $now - strtotime( $s['last_seen'] ) ) <= 604800;
                                $is_pro = in_array( $s['plan_status'], array( 'pro', 'enterprise' ), true );
                            ?>
                                <tr data-domain="<?php echo htmlspecialchars( strtolower( $s['domain'] ) ); ?>" 
                                    data-email="<?php echo htmlspecialchars( strtolower( $s['admin_email'] ?? '' ) ); ?>"
                                    data-server="<?php echo htmlspecialchars( strtolower( $s['server_software'] ?? '' ) ); ?>"
                                    data-plan="<?php echo $is_pro ? 'pro' : 'free'; ?>"
                                    data-active="<?php echo $is_active ? 'active' : 'inactive'; ?>">
                                    <td>
                                        <span class="dot <?php echo $is_active ? 'dot-green' : 'dot-gray'; ?>" title="<?php echo $is_active ? 'Active' : 'Inactive (> 7 days)'; ?>"></span>
                                    </td>
                                    <td>
                                        <div class="domain-cell">
                                            <a href="<?php echo htmlspecialchars( $s['site_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="domain-name">
                                                <?php echo htmlspecialchars( $s['domain'] ); ?> ↗
                                            </a>
                                            <span class="domain-url"><?php echo htmlspecialchars( $s['ip_address'] ?? 'IP Unknown' ); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ( ! empty( $s['admin_email'] ) ) : ?>
                                            <a href="mailto:<?php echo htmlspecialchars( $s['admin_email'] ); ?>" style="color:var(--text-main); text-decoration:none;">
                                                <?php echo htmlspecialchars( $s['admin_email'] ); ?>
                                            </a>
                                        <?php else : ?>
                                            <span style="color:var(--text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $is_pro ) : ?>
                                            <span class="badge badge-pro">👑 PRO</span>
                                        <?php else : ?>
                                            <span class="badge badge-free">FREE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-weight:600;">v<?php echo htmlspecialchars( $s['wp_version'] ?: '?' ); ?></span>
                                        <span style="color:var(--text-muted); font-size:12px;">/ PHP <?php echo htmlspecialchars( $s['php_version'] ?: '?' ); ?></span>
                                    </td>
                                    <td>
                                        <strong>v<?php echo htmlspecialchars( $s['plugin_version'] ?: '1.2.0' ); ?></strong>
                                    </td>
                                    <td>
                                        <span style="font-size:12.5px; color:var(--text-muted);">
                                            <?php echo htmlspecialchars( explode( '/', $s['server_software'] ?? 'Server' )[0] ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format( (int) $s['ping_count'] ); ?></strong>
                                    </td>
                                    <td>
                                        <span title="<?php echo htmlspecialchars( $s['last_seen'] ); ?>">
                                            <?php echo time_ago( $s['last_seen'] ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?action=delete&id=<?php echo $s['id']; ?>" onclick="return confirm('Delete telemetry for <?php echo addslashes( $s['domain'] ); ?>?');" style="color:#ef4444; font-size:12px; text-decoration:none;">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Theme toggle
        const themeBtn = document.getElementById('theme-toggle');
        if (themeBtn) {
            const savedTheme = localStorage.getItem('nik_tracker_theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                themeBtn.textContent = '☀️ Light';
            }
            themeBtn.addEventListener('click', () => {
                const isDark = document.body.classList.toggle('dark-mode');
                localStorage.setItem('nik_tracker_theme', isDark ? 'dark' : 'light');
                themeBtn.textContent = isDark ? '☀️ Light' : '🌙 Dark';
            });
        }

        // Search & Filter
        const searchInput = document.getElementById('search-input');
        const filterBtns = document.querySelectorAll('.filter-btn');
        const tableRows = document.querySelectorAll('#sites-table tbody tr');

        let currentFilter = 'all';

        function applyFilter() {
            const q = searchInput ? searchInput.value.toLowerCase().trim() : '';
            tableRows.forEach(row => {
                const domain = row.getAttribute('data-domain') || '';
                const email = row.getAttribute('data-email') || '';
                const server = row.getAttribute('data-server') || '';
                const plan = row.getAttribute('data-plan') || '';
                const active = row.getAttribute('data-active') || '';

                let matchesSearch = !q || domain.includes(q) || email.includes(q) || server.includes(q);
                let matchesFilter = true;

                if (currentFilter === 'active') matchesFilter = (active === 'active');
                if (currentFilter === 'pro') matchesFilter = (plan === 'pro');
                if (currentFilter === 'free') matchesFilter = (plan === 'free');

                row.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFilter);
        }

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.getAttribute('data-filter');
                applyFilter();
            });
        });
    </script>
</body>
</html>