<?php
/**
 * Plugin Name: Elementor Kit Backup Manager
 * Description: A professional version control tool for Elementor Global Styles. Capture, preview, and restore site-wide colors, typography, and custom CSS with ease.
 * Version: 1.9.2
 * Author: Amirhossein Hosseinpour
 * Author URI: https://amirhp.com
 * Requires Plugins: elementor
 * Elementor tested up to: 3.35.4
 * Elementor Pro tested up to: 3.35.0
 * Text Domain: elementor-kit-backup
 * Domain Path: /languages
 * Copyright: (c) AmirhpCom, All rights reserved.
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * @Date: 2026/02/16 02:38:45
 * @Last modified by: amirhp-com <its@amirhp.com>
 * @Last modified time: 2026/02/16 04:42:32
*/

defined("ABSPATH") or die("Elementor Kit Backup Manager :: Unauthorized Access!" . PHP_EOL . "Developed by amirhp.com [https://amirhp.com]");

class ElementorKitBackup {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_action_links' ] );
    }

    public function add_action_links( $links ) {
        $settings_link = '<a href="admin.php?page=elementor-kit-backup">' . esc_html__( 'Settings', 'elementor-kit-backup' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function add_menu() {
        add_submenu_page(
            'elementor',
            esc_html__( 'Kit Backups', 'elementor-kit-backup' ),
            esc_html__( 'Kit Backups', 'elementor-kit-backup' ),
            'manage_options',
            'elementor-kit-backup',
            [ $this, 'render_ui' ]
        );
    }

    public function render_ui() {
        $i18n = $this->get_translations();
        $this->enqueue_assets();
        ?>
        <div class="wrap">
            <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
            <script src="https://cdn.tailwindcss.com"></script>
            <style>
                .ekb-wrap, .ekb-modal, .rtl .ekb-modal button, .rtl h1, .rtl h2, .rtl h3, .rtl h4, .rtl h5, .rtl h6{font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
                .ekb-wrap { background: #f9fafb; min-height: calc(100vh - 32px); }
                .force-ltr{direction: ltr !important; unicode-bidi: plaintext;}
                .ekb-loader { display:none; position: fixed; inset: 0; background: rgba(255,255,255,0.8); z-index: 9999; align-items: center; justify-content: center; }
                .ekb-modal { display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9998; align-items: center; justify-content: center; padding: 20px; }
                .ekb-modal-content { background: white; width: 100%; max-width: 800px; max-height: 90vh; border-radius: 20px; overflow: hidden; display: flex; flex-direction: column; }
                .ekb-code { background: #1e293b; color: #f8fafc; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 12px; overflow-x: auto; white-space: pre-wrap; direction: ltr; unicode-bidi: plaintext;}
                .ekb-color-circle { width: 24px; height: 24px; border-radius: 50%; border: 2px solid #e2e8f0; }
                #ekb-custom-dialog { display:none; }
            </style>

            <!-- AJAX Loader -->
            <div class="ekb-loader" id="ekb-ajax-loader">
                <div class="flex flex-col items-center gap-3">
                    <div class="w-10 h-10 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-sm font-bold text-indigo-900"><?php echo esc_html($i18n['loading']); ?></span>
                </div>
            </div>

            <!-- Custom Dialog (Alert/Confirm) -->
            <div class="ekb-modal" id="ekb-custom-dialog">
                <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 animate-in fade-in zoom-in duration-200">
                    <h3 id="ekb-dialog-title" class="text-lg font-black text-gray-800 mb-2"></h3>
                    <p id="ekb-dialog-msg" class="text-sm text-gray-500 mb-6"></p>
                    <div class="flex justify-end gap-3">
                        <button id="ekb-dialog-cancel" class="px-4 py-2 text-sm font-bold text-gray-400 hover:text-gray-600 uppercase"><?php echo esc_html($i18n['cancel']); ?></button>
                        <button id="ekb-dialog-confirm" class="px-6 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold shadow-md hover:bg-indigo-700 active:scale-95 transition-all uppercase"><?php echo esc_html($i18n['ok']); ?></button>
                    </div>
                </div>
            </div>

            <!-- Preview Modal -->
            <div class="ekb-modal" id="ekb-preview-modal">
                <div class="ekb-modal-content shadow-2xl animate-in fade-in zoom-in duration-200">
                    <header class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50">
                        <div>
                            <h3 class="font-black text-gray-800 text-sm uppercase tracking-wider" id="preview-title"></h3>
                            <p class="text-[10px] text-gray-400 font-bold" id="preview-date"></p>
                        </div>
                        <div class="flex items-center gap-2">
                             <button id="ekb-modal-restore" class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-[10px] font-bold uppercase shadow-sm hover:bg-emerald-700 transition-all"><?php echo esc_html($i18n['restore']); ?></button>
                             <button id="ekb-modal-download" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-[10px] font-bold uppercase shadow-sm hover:bg-blue-700 transition-all"><?php echo esc_html($i18n['download']); ?></button>
                             <button id="ekb-close-preview" class="text-gray-400 hover:text-gray-600 p-2 ml-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </header>
                    <div class="flex-1 overflow-y-auto p-6 space-y-6" id="preview-body">
                        <!-- Content injected by app.js -->
                    </div>
                </div>
            </div>

            <div class="ekb-wrap">
                <header class="bg-white border-b border-gray-200 px-8 py-5 flex items-center justify-between shadow-sm sticky top-0 z-10">
                    <div>
                        <h1 class="text-xl font-black text-gray-800 m-0 leading-none"><?php esc_html_e('Elementor Kit Backup', 'elementor-kit-backup'); ?></h1>
                        <p class="text-[10px] font-bold text-indigo-500 uppercase mt-1"><?php esc_html_e('By Amirhossein Hosseinpour', 'elementor-kit-backup'); ?></p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter"><?php echo esc_html($i18n['activeKit']); ?></span>
                            <p id="ekb-active-kit-display" class="text-sm font-bold text-gray-700 italic">...</p>
                            <div class="flex justify-end gap-3 mt-1">
                                <a href="#" id="ekb-edit-kit-link" target="_blank" class="text-[9px] font-bold text-indigo-500 uppercase hover:underline"><?php echo esc_html($i18n['editKit']); ?></a>
                                <span class="text-gray-200">|</span>
                                <a href="<?php echo admin_url('edit.php?post_type=elementor_library&tabs_group=library&elementor_library_type=kit'); ?>" target="_blank" class="text-[9px] font-bold text-gray-400 uppercase hover:underline"><?php echo esc_html($i18n['manageKits']); ?></a>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="mx-auto p-8 space-y-8">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col md:flex-row items-center gap-4">
                        <div class="flex-1">
                            <h2 class="text-base font-bold text-gray-800 mb-1"><?php echo esc_html($i18n['createBackup']); ?></h2>
                            <p class="text-xs text-gray-500"><?php echo esc_html($i18n['guide']); ?></p>
                        </div>
                        <div class="flex gap-2 w-full md:w-auto">
                            <input type="text" id="ekb-new-name" placeholder="<?php echo esc_attr($i18n['placeholder']); ?>" class="flex-1 md:w-64 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                            <button id="ekb-btn-save" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl font-bold text-sm shadow-md transition-all active:scale-95"><?php echo esc_html($i18n['save']); ?></button>
                            <button id="ekb-btn-import-trigger" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-2.5 rounded-xl border border-gray-200" title="<?php echo esc_attr($i18n['import']); ?>">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            </button>
                            <input type="file" id="ekb-file-input" class="hidden" accept=".json">
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                            <h3 class="font-black text-gray-800 text-xs uppercase"><?php echo esc_html($i18n['history']); ?></h3>
                            <button id="ekb-btn-export-all" class="text-[10px] font-bold text-indigo-600 uppercase hover:underline"><?php echo esc_html($i18n['exportAll']); ?></button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50 text-[10px] font-bold text-gray-400 uppercase border-b border-gray-100">
                                    <tr>
                                        <th class="px-6 py-4"><?php echo esc_html($i18n['name']); ?></th>
                                        <th class="px-6 py-4"><?php echo esc_html($i18n['user']); ?></th>
                                        <th class="px-6 py-4"><?php echo esc_html($i18n['date']); ?></th>
                                        <th class="px-6 py-4"><?php echo esc_html($i18n['size']); ?></th>
                                        <th class="px-6 py-4 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody id="ekb-history-list" class="divide-y divide-gray-100">
                                    <!-- Populated via jQuery -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <footer class="text-center py-6 force-ltr">
                        <div class="flex flex-col items-center gap-2">
                            <div class="flex items-center justify-center gap-6">
                                <a href="https://amirhp.com" target="_blank" class="text-[10px] font-bold text-gray-400 uppercase hover:text-indigo-500 transition-colors tracking-widest">
                                    Amirhp.com
                                </a>
                                <a href="mailto:its@amirhp.com" class="text-[10px] font-bold text-gray-400 uppercase hover:text-indigo-500 transition-colors tracking-widest">
                                    Support
                                </a>
                                <a href="https://github.com/amirhp-com/elementor-kit-backup" target="_blank" class="text-[10px] font-bold text-gray-400 uppercase hover:text-indigo-500 transition-colors tracking-widest">
                                    GitHub
                                </a>
                            </div>
                            <p class="text-[11px] text-gray-500 uppercase tracking-tighter">© <?php echo date('Y'); ?> Amirhossein Hosseinpour. All rights reserved.</p>
                        </div>
                    </footer>
                </main>
            </div>
        </div>
        <?php
    }

    public function enqueue_assets( $hook="" ) {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'ekb-app', plugin_dir_url( __FILE__ ) . 'app.js', ['jquery'], current_time("timestamp"), true );
        wp_localize_script( 'ekb-app', 'ekbConfig', [
            'api' => esc_url_raw( rest_url( 'ekb/v1/data' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'i18n' => $this->get_translations()
        ]);
    }

    private function get_translations() {
        return [
            'activeKit' => esc_html__( 'Current Site Kit', 'elementor-kit-backup' ),
            'editKit' => esc_html__( 'Edit Settings', 'elementor-kit-backup' ),
            'manageKits' => esc_html__( 'Manage Kits', 'elementor-kit-backup' ),
            'createBackup' => esc_html__( 'Create Backup', 'elementor-kit-backup' ),
            'createBackupDone' => esc_html__( 'New Backup Created successfully.', 'elementor-kit-backup' ),
            'createBackupErr' => esc_html__( 'Could not create backup.', 'elementor-kit-backup' ),
            'placeholder' => esc_html__( 'Backup Name...', 'elementor-kit-backup' ),
            'save' => esc_html__( 'Create Backup', 'elementor-kit-backup' ),
            'history' => esc_html__( 'Version History (Limit 20)', 'elementor-kit-backup' ),
            'name' => esc_html__( 'Name', 'elementor-kit-backup' ),
            'user' => esc_html__( 'Author', 'elementor-kit-backup' ),
            'date' => esc_html__( 'Timestamp', 'elementor-kit-backup' ),
            'size' => esc_html__( 'Size', 'elementor-kit-backup' ),
            'restore' => esc_html__( 'Restore', 'elementor-kit-backup' ),
            'download' => esc_html__( 'Export', 'elementor-kit-backup' ),
            'delete' => esc_html__( 'Delete', 'elementor-kit-backup' ),
            'preview' => esc_html__( 'Preview', 'elementor-kit-backup' ),
            'loading' => esc_html__( 'Working...', 'elementor-kit-backup' ),
            'confirmRestore' => esc_html__( 'Are you sure? This will overwrite your active Global Styles.', 'elementor-kit-backup' ),
            'confirmDelete' => esc_html__( 'Remove this version forever?', 'elementor-kit-backup' ),
            'import' => esc_html__( 'Import JSON', 'elementor-kit-backup' ),
            'importWarning' => esc_html__( 'File successfully imported. Note: You must click Restore to activate this kit.', 'elementor-kit-backup' ),
            'bundleSuccess' => esc_html__( 'Archive bundle successfully merged into history.', 'elementor-kit-backup' ),
            'exportAll' => esc_html__( 'Download Bundle', 'elementor-kit-backup' ),
            'guide' => esc_html__( 'Snapshots global colors, fonts, and custom CSS settings.', 'elementor-kit-backup' ),
            'success' => esc_html__( 'Success!', 'elementor-kit-backup' ),
            'error' => esc_html__( 'Something went wrong.', 'elementor-kit-backup' ),
            'invalidKit' => esc_html__( 'Integrity Check Failed: Invalid Elementor Kit Structure.', 'elementor-kit-backup' ),
            'colors' => esc_html__( 'Global Colors', 'elementor-kit-backup' ),
            'typography' => esc_html__( 'Global Fonts', 'elementor-kit-backup' ),
            'css' => esc_html__( 'Custom CSS', 'elementor-kit-backup' ),
            'noData' => esc_html__( 'Not set in this backup.', 'elementor-kit-backup' ),
            'cancel' => esc_html__( 'Cancel', 'elementor-kit-backup' ),
            'ok' => esc_html__( 'OK', 'elementor-kit-backup' ),
            'details' => esc_html__( 'Kit Components', 'elementor-kit-backup' ),
            'itemCount' => esc_html__( 'Detected Items', 'elementor-kit-backup' )
        ];
    }

    public function register_routes() {
        register_rest_route( 'ekb/v1', '/data', [
            [
                'methods' => 'GET',
                'callback' => [ $this, 'get_data' ],
                'permission_callback' => function () { return current_user_can( 'manage_options' ); }
            ],
            [
                'methods' => 'POST',
                'callback' => [ $this, 'handle_action' ],
                'permission_callback' => function () { return current_user_can( 'manage_options' ); }
            ]
        ]);
    }

    public function get_data() {
        $kit_id = get_option( 'elementor_active_kit' );
        $kit_post = get_post( $kit_id );
        return [
            'activeKit' => [
                'id' => (int)$kit_id,
                'title' => $kit_post ? $kit_post->post_title : 'N/A',
                'editUrl' => admin_url('post.php?post=' . $kit_id . '&action=elementor')
            ],
            'backups' => get_option( 'ekb_backups_list', [] )
        ];
    }

    public function handle_action( $request ) {
        $action = $request->get_param('action');
        $kit_id = (int)get_option( 'elementor_active_kit' );
        $backups = get_option( 'ekb_backups_list', [] );
        $user = wp_get_current_user();

        switch($action) {
            case 'create':
                $name = $request->get_param('name');
                $settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
                $json = json_encode($settings ? $settings : []);
                
                array_unshift($backups, [
                    'id' => uniqid(),
                    'name' => $name ? sanitize_text_field($name) : 'v' . date('Ymd-Hi'),
                    'timestamp' => current_time('mysql'),
                    'data' => $json,
                    'user' => $user->display_name,
                    'size' => round(strlen($json) / 1024, 2) . ' KB'
                ]);
                $backups = array_slice($backups, 0, 20);
                update_option( 'ekb_backups_list', $backups );
                return [ 'success' => true, 'backups' => $backups ];

            case 'import':
                $payload = $request->get_param('backup');
                array_unshift($backups, [
                    'id' => uniqid(),
                    'name' => sanitize_text_field($payload['name']) . ' (Imported)',
                    'timestamp' => current_time('mysql'),
                    'data' => $payload['data'],
                    'user' => $user->display_name,
                    'size' => round(strlen($payload['data']) / 1024, 2) . ' KB'
                ]);
                $backups = array_slice($backups, 0, 20);
                update_option( 'ekb_backups_list', $backups );
                return [ 'success' => true, 'backups' => $backups ];

            case 'bundle_import':
                $bundle = $request->get_param('bundle');
                if (!is_array($bundle)) return new WP_Error('invalid', 'Invalid bundle format');
                
                // Merge and deduplicate by timestamp
                $merged = array_merge($bundle, $backups);
                $unique = [];
                foreach ($merged as $item) {
                $unique[$item['timestamp'] . $item['name']] = $item;
                }
                $backups = array_slice(array_values($unique), 0, 20);
                update_option( 'ekb_backups_list', $backups );
                return [ 'success' => true, 'backups' => $backups ];

            case 'restore':
                $id = $request->get_param('id');
                foreach($backups as $b) {
                    if ($b['id'] === $id) {
                        $data = json_decode($b['data'], true);
                        update_post_meta( $kit_id, '_elementor_page_settings', $data );
                        
                        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance) {
                            if (isset(\Elementor\Plugin::$instance->documents)) {
                                $document = \Elementor\Plugin::$instance->documents->get($kit_id);
                                if ($document && method_exists($document, 'get_post_type_instance')) {
                                    $post_type_instance = $document->get_post_type_instance();
                                    if ($post_type_instance && method_exists($post_type_instance, 'clear_cache')) {
                                        $post_type_instance->clear_cache($kit_id);
                                    }
                                }
                            }
                        }
                        return [ 'success' => true ];
                    }
                }
                return new WP_Error('not_found', 'Backup not found');

            case 'delete':
                $id = $request->get_param('id');
                $backups = array_values(array_filter($backups, function($b) use ($id) { return $b['id'] !== $id; }));
                update_option( 'ekb_backups_list', $backups );
                return [ 'success' => true, 'backups' => $backups ];
        }
    }
}
new ElementorKitBackup();

/*##################################################
Lead Developer: [amirhp-com](https://amirhp.com/)
##################################################*/