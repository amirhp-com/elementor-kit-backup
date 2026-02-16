jQuery(document).ready(function ($) {
    var API = ekbConfig.api;
    var NONCE = ekbConfig.nonce;
    var i18n = ekbConfig.i18n;
    var localBackups = [];

    // Custom Modal Helpers
    function ekbDialog(title, msg, type, callback) {
        $('#ekb-dialog-title').text(title);
        $('#ekb-dialog-msg').text(msg);

        if (type === 'alert') {
            $('#ekb-dialog-cancel').hide();
        } else {
            $('#ekb-dialog-cancel').show();
        }

        $('#ekb-custom-dialog').css('display', 'flex');

        $('#ekb-dialog-confirm').off('click').on('click', function () {
            $('#ekb-custom-dialog').hide();
            if (callback) callback(true);
        });

        $('#ekb-dialog-cancel').off('click').on('click', function () {
            $('#ekb-custom-dialog').hide();
            if (callback) callback(false);
        });
    }

    function toggleLoader(show) {
        $('#ekb-ajax-loader').css('display', show ? 'flex' : 'none');
    }

    function refreshData() {
        toggleLoader(true);
        $.ajax({
            url: API,
            method: 'GET',
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', NONCE); },
            success: function (res) {
                localBackups = res.backups;
                $('#ekb-active-kit-display').text(res.activeKit.title + ' (#' + res.activeKit.id + ')');
                $('#ekb-edit-kit-link').attr('href', res.activeKit.editUrl);
                renderList(localBackups);
                toggleLoader(false);
            }
        });
    }

    function renderList(list) {
        var $list = $('#ekb-history-list');
        $list.empty();
        if (!list || list.length === 0) {
            $list.append('<tr><td colspan="5" class="p-12 text-center text-gray-400 text-xs italic">No versions saved yet.</td></tr>');
            return;
        }
        list.forEach(function (item) {
            var nameStr = item.name ? item.name : 'Untitled';
            var userStr = item.user ? item.user : '—';
            $list.append(
                '<tr class="hover:bg-gray-50 transition-colors group">' +
                '<td class="px-6 py-4"><button data-id="' + item.id + '" class="ekb-preview-act text-sm font-bold text-gray-700 hover:text-indigo-600 transition-colors">' + nameStr + '</button></td>' +
                '<td class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase">' + userStr + '</td>' +
                '<td class="px-6 py-4 text-[10px] font-medium text-gray-400 uppercase">' + item.timestamp + '</td>' +
                '<td class="px-6 py-4 text-[10px] font-black text-gray-300 uppercase">' + item.size + '</td>' +
                '<td class="px-6 py-4 text-right">' +
                '<div class="flex justify-end gap-2">' +
                '<button data-id="' + item.id + '" class="ekb-preview-act bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase hover:bg-indigo-600 hover:text-white transition-all">' + i18n.preview + '</button>' +
                '<button data-id="' + item.id + '" class="ekb-restore-act bg-emerald-50 text-emerald-600 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase hover:bg-emerald-600 hover:text-white transition-all">' + i18n.restore + '</button>' +
                '<button data-id="' + item.id + '" class="ekb-download-act bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase hover:bg-blue-600 hover:text-white transition-all">' + i18n.download + '</button>' +
                '<button data-id="' + item.id + '" class="ekb-delete-act bg-gray-50 text-gray-400 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase hover:bg-rose-600 hover:text-white transition-all">' + i18n.delete + '</button>' +
                '</div>' +
                '</td>' +
                '</tr>'
            );
        });
    }

    function verifyIntegrity(data) {
        if (typeof data !== 'object' || data === null) return false;
        var expectedKeys = ['system_colors', 'custom_colors', 'system_typography', 'custom_typography', 'custom_css'];
        return expectedKeys.some(k => data.hasOwnProperty(k));
    }

    // --- PREVIEW LOGIC ---
    $(document).on('click', '.ekb-preview-act', function () {
        var id = $(this).data('id');
        var item = localBackups.find(b => b.id === id);
        if (!item) return;

        var data = JSON.parse(item.data);
        $('#preview-title').text(item.name);
        $('#preview-date').text(item.timestamp + ' | Author: ' + (item.user || 'N/A'));

        $('#ekb-modal-restore').attr('data-id', item.id);
        $('#ekb-modal-download').attr('data-id', item.id);

        var $body = $('#preview-body').empty();

        // 0. Metadata / Extra Details
        var colorCount = (data.system_colors || []).length + (data.custom_colors || []).length;
        var typoCount = (data.system_typography || []).length + (data.custom_typography || []).length;

        var $stats = $('<div class="grid grid-cols-3 gap-4 mb-6"></div>');
        var statsData = [
            { label: 'Colors', val: colorCount },
            { label: 'Typography', val: typoCount },
            { label: 'CSS Lines', val: (data.custom_css || '').split('\n').filter(l => l.trim()).length }
        ];
        statsData.forEach(s => {
            $stats.append('<div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100 text-center"><p class="text-[10px] font-black text-indigo-300 uppercase">' + s.label + '</p><p class="text-xl font-black text-indigo-600">' + s.val + '</p></div>');
        });
        $body.append($stats);

        // 1. Colors
        var colors = (data.system_colors || []).concat(data.custom_colors || []);
        var $colorSection = $('<div class="space-y-3"><h4 class="text-xs font-black text-gray-400 uppercase">' + i18n.colors + '</h4></div>');
        if (colors.length > 0) {
            var $grid = $('<div class="grid grid-cols-2 md:grid-cols-4 gap-4 force-ltr"></div>');
            colors.forEach(function (c) {
                $grid.append(
                    '<div class="flex items-center gap-3 bg-gray-50 p-2 rounded-xl border border-gray-100">' +
                    '<div class="ekb-color-circle" style="background:' + (c.color || '#ccc') + '"></div>' +
                    '<div class="overflow-hidden"><p class="text-[10px] font-bold text-gray-700 truncate">' + (c.title || 'Untitled') + '</p><p class="text-[9px] text-gray-400 font-mono">' + (c.color || 'N/A') + '</p></div>' +
                    '</div>'
                );
            });
            $colorSection.append($grid);
        } else {
            $colorSection.append('<p class="text-xs text-gray-300 italic">' + i18n.noData + '</p>');
        }
        $body.append($colorSection);

        // 2. Typography
        var fonts = (data.system_typography || []).concat(data.custom_typography || []);
        var $typoSection = $('<div class="space-y-3 border-t border-gray-100 pt-6"><h4 class="text-xs font-black text-gray-400 uppercase">' + i18n.typography + '</h4></div>');
        if (fonts.length > 0) {
            var $list = $('<div class="space-y-2"></div>');
            fonts.forEach(function (f) {
                var weight = f.typography_font_weight || 'Regular';
                var size = f.typography_font_size ? f.typography_font_size.size + (f.typography_font_size.unit || 'px') : 'N/A';
                $list.append(
                    '<div class="flex justify-between items-center bg-gray-50 px-4 py-2 rounded-lg border border-gray-100 force-ltr">' +
                    '<span class="text-[11px] font-bold text-gray-700">' + (f.title || 'Untitled') + '</span>' +
                    '<span class="text-[10px] text-gray-400">' + (f.typography_font_family || 'Inherit') + ' (' + weight + ') &bull; ' + size + '</span>' +
                    '</div>'
                );
            });
            $typoSection.append($list);
        } else {
            $typoSection.append('<p class="text-xs text-gray-300 italic">' + i18n.noData + '</p>');
        }
        $body.append($typoSection);

        // 3. Custom CSS
        var css = data.custom_css || '';
        var $cssSection = $('<div class="space-y-3 border-t border-gray-100 pt-6"><h4 class="text-xs font-black text-gray-400 uppercase">' + i18n.css + '</h4></div>');
        if (css) {
            $cssSection.append('<div class="ekb-code">' + css.replace(/</g, "&lt;").replace(/>/g, "&gt;") + '</div>');
        } else {
            $cssSection.append('<p class="text-xs text-gray-300 italic">' + i18n.noData + '</p>');
        }
        $body.append($cssSection);

        $('#ekb-preview-modal').css('display', 'flex');
    });

    $('#ekb-close-preview, #ekb-preview-modal').on('click', function (e) {
        if (e.target === this || e.currentTarget.id === 'ekb-close-preview') {
            $('#ekb-preview-modal').hide();
        }
    });

    $('#ekb-modal-restore').on('click', function () {
        var id = $(this).data('id');
        $('.ekb-restore-act[data-id="' + id + '"]').first().trigger('click');
    });

    $('#ekb-modal-download').on('click', function () {
        var id = $(this).data('id');
        $('.ekb-download-act[data-id="' + id + '"]').first().trigger('click');
    });

    $('#ekb-btn-save').on('click', function () {
        var name = $('#ekb-new-name').val();
        toggleLoader(true);
        $.ajax({
            url: API,
            method: 'POST',
            contentType: 'application/json',
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', NONCE); },
            data: JSON.stringify({ action: 'create', name: name }),
            success: function (res) {
                $('#ekb-new-name').val('');
                localBackups = res.backups;
                renderList(localBackups);
                toggleLoader(false);
                ekbDialog(i18n.success, i18n.createBackupDone, 'alert');
            },
            error: function () {
                ekbDialog(i18n.error, i18n.createBackupErr, 'alert');
                toggleLoader(false);
            }
        });
    });

    $(document).on('click', '.ekb-restore-act', function () {
        ekbDialog('Confirm Restore', i18n.confirmRestore, 'confirm', function (ok) {
            if (!ok) return;
            var id = $(this).data('id');
            toggleLoader(true);
            $.ajax({
                url: API,
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', NONCE); },
                data: JSON.stringify({ action: 'restore', id: id }),
                success: function () { window.location.reload(); },
                error: function () {
                    ekbDialog(i18n.error, 'Restore failed.', 'alert');
                    toggleLoader(false);
                }
            });
        }.bind(this));
    });

    $(document).on('click', '.ekb-delete-act', function () {
        ekbDialog('Confirm Deletion', i18n.confirmDelete, 'confirm', function (ok) {
            if (!ok) return;
            var id = $(this).data('id');
            toggleLoader(true);
            $.ajax({
                url: API,
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', NONCE); },
                data: JSON.stringify({ action: 'delete', id: id }),
                success: function (res) {
                    localBackups = res.backups;
                    renderList(localBackups);
                    toggleLoader(false);
                }
            });
        }.bind(this));
    });

    $(document).on('click', '.ekb-download-act', function () {
        var id = $(this).data('id');
        var item = localBackups.find(function (b) { return b.id === id; });
        if (!item) return;

        try {
            var fileName = (item.name || 'backup').replace(/[^a-z0-9]/gi, '_').toLowerCase();
            var content = typeof item.data === 'string' ? item.data : JSON.stringify(item.data);
            var blob = new Blob([content], { type: 'application/json' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'kit-' + fileName + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        } catch (err) {
            console.error(err);
            ekbDialog(i18n.error, 'Export failed.', 'alert');
        }
    });

    $('#ekb-btn-export-all').on('click', function () {
        if (localBackups.length === 0) return;
        var blob = new Blob([JSON.stringify(localBackups)], { type: 'application/json' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'kit-backups-bundle-' + new Date().toISOString().split('T')[0] + '.json';
        a.click();
    });

    $('#ekb-btn-import-trigger').on('click', function () { $('#ekb-file-input').trigger('click'); });

    $('#ekb-file-input').on('change', function (e) {
        var file = e.target.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (ev) {
            try {
                var raw = JSON.parse(ev.target.result);
                // Detection: Is it a Bundle (Array) or Single Kit (Object)?
                if (Array.isArray(raw)) {
                    toggleLoader(true);
                    $.ajax({
                        url: API, method: 'POST', contentType: 'application/json',
                        beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', NONCE); },
                        data: JSON.stringify({ action: 'bundle_import', bundle: raw }),
                        success: function (res) { localBackups = res.backups; renderList(localBackups); toggleLoader(false); ekbDialog(i18n.success, i18n.bundleSuccess, 'alert'); },
                        error: function () { ekbDialog(i18n.error, 'Bundle merge failed.', 'alert'); toggleLoader(false); }
                    });
                } else {
                    var settingsObject = typeof raw === 'string' ? JSON.parse(raw) : raw;

                    if (!verifyIntegrity(settingsObject)) {
                        ekbDialog('Error', i18n.invalidKit, 'alert');
                        return;
                    }

                    var payload = {
                        name: file.name.replace('.json', ''),
                        data: JSON.stringify(settingsObject)
                    };

                    toggleLoader(true);
                    $.ajax({
                        url: API,
                        method: 'POST',
                        contentType: 'application/json',
                        beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', NONCE); },
                        data: JSON.stringify({ action: 'import', backup: payload }),
                        success: function (res) {
                            localBackups = res.backups;
                            renderList(localBackups);
                            toggleLoader(false);
                            ekbDialog(i18n.success, i18n.importWarning, 'alert');
                        },
                        error: function () {
                            ekbDialog(i18n.error, 'Import failed.', 'alert');
                            toggleLoader(false);
                        }
                    });
                }
            } catch (err) { ekbDialog(i18n.error, 'Invalid file format.', 'alert'); }
        };
        reader.readAsText(file);
    });

    refreshData();
});
