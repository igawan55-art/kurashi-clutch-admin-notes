jQuery(document).ready(function($) {
    /* ==========================================================================
       Wiki System: Drag and Drop Reordering
       ========================================================================== */
    if (typeof $.fn.sortable !== 'undefined' && $('.kcan-wiki-list tbody').length) {
        var $tbody = $('.kcan-wiki-list tbody');
        var childrenToMove = [];

        $tbody.sortable({
            handle: '.kcan-drag-handle',
            items: '> tr',
            cursor: 'grabbing',
            helper: function(e, tr) {
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function(index) { $(this).width($originals.eq(index).width()); });
                return $helper;
            },
            start: function(e, ui) {
                var $row = ui.item;
                var depth = parseInt($row.data('depth'), 10);
                childrenToMove = [];
                $row.nextAll('tr').each(function() {
                    if (parseInt($(this).data('depth'), 10) > depth) {
                        childrenToMove.push($(this));
                        $(this).hide();
                    } else { return false; }
                });
            },
            stop: function(e, ui) {
                var $insertAfter = ui.item;
                $.each(childrenToMove, function(i, $child) {
                    $child.insertAfter($insertAfter).show();
                    $insertAfter = $child;
                });
                var order = [];
                $tbody.find('tr').each(function() {
                    var id = $(this).data('id');
                    if (id) order.push(id);
                });
                $('.kcan-wiki-spinner').addClass('is-active');
                $.post(ajaxurl, {
                    action: 'kcan_wiki_update_order', order: order, nonce: $('#kcan_wiki_order_nonce').val()
                }, function(res) { $('.kcan-wiki-spinner').removeClass('is-active'); });
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    /* ==========================================================================
       Dashboard Widget Scripts
       ========================================================================== */
    document.querySelectorAll('.kcan-btn-edit').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var wrapper = this.closest('.kcan-dashboard-wrapper');
            if (wrapper) {
                wrapper.querySelector('.kcan-mode-view').style.display = 'none';
                wrapper.querySelector('.kcan-mode-edit').style.display = 'block';
            }
        });
    });

    document.querySelectorAll('.kcan-btn-cancel').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var wrapper = this.closest('.kcan-dashboard-wrapper');
            if (wrapper) {
                wrapper.querySelector('.kcan-mode-edit').style.display = 'none';
                wrapper.querySelector('.kcan-mode-view').style.display = 'block';
            }
        });
    });

    document.querySelectorAll('.kcan-btn-add-item').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var wrapper = this.closest('.kcan-dashboard-wrapper');
            var container = wrapper.querySelector('.kcan-mode-edit .kcan-list-container');
            var uniqueId = 'new_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
            var div = document.createElement('div');
            div.className = 'kcan-list-item';
            div.innerHTML = '<input type="checkbox" name="kcan_list_checked[' + uniqueId + ']" value="1" checked> ' +
                            '<input type="text" name="kcan_list_text[' + uniqueId + ']" value=""> ' +
                            '<span class="kcan-remove-item">&times;</span>';
            container.appendChild(div);
        });
    });

    document.body.addEventListener('click', function(e) {
        if (e.target.classList.contains('kcan-remove-item')) {
            e.preventDefault();
            var item = e.target.closest('.kcan-list-item');
            if (item) item.remove();
        }
    });

    /* ==========================================================================
       Admin Notices (Meta Box) Scripts
       ========================================================================== */
    var box = document.getElementById('kcan_alerts_meta_box');
    if (box && box.parentElement) {
        box.parentElement.prepend(box);
    }
    var container = document.querySelector('.kcan-alerts-meta-container');
    if (box && container) {
        var borderColor = container.getAttribute('data-border-color');
        var bgColor = container.getAttribute('data-bg-color');
        if (borderColor && bgColor) {
            box.style.setProperty('border', '1px solid ' + borderColor, 'important');
            box.style.setProperty('border-left', '4px solid ' + borderColor, 'important');
            box.style.setProperty('background-color', bgColor, 'important');
        }
    }

    /* ==========================================================================
       Settings Page Scripts
       ========================================================================== */
    var tabs = document.querySelectorAll('.nav-tab');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('nav-tab-active'));
            document.querySelectorAll('.kcan-tab-content').forEach(c => c.classList.remove('active'));
            tab.classList.add('nav-tab-active');
            var target = document.querySelector(tab.getAttribute('href'));
            if (target) target.classList.add('active');
        });
    });

    document.querySelectorAll('.kcan-remove-alert-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var confirmMsg = (typeof kcanData !== 'undefined' && kcanData.confirmDeleteAlert) ? kcanData.confirmDeleteAlert : 'Are you sure?';
            if (confirm(confirmMsg)) this.closest('.kcan-alert-row').remove();
        });
    });

    function toggleSpecificRules() {
        document.querySelectorAll('.kcan-alert-row').forEach(function(row) {
            var specificRadio = row.querySelector('input[type="radio"][value="specific"]');
            var rulesRow = row.querySelector('.kcan-specific-rules-row');
            if (specificRadio && rulesRow) rulesRow.style.display = specificRadio.checked ? '' : 'none';
        });
    }
    document.querySelectorAll('input[type="radio"][name$="[rule]"]').forEach(function(radio) {
        radio.addEventListener('change', toggleSpecificRules);
    });
    toggleSpecificRules(); 

    /* ==========================================================================
       Wiki System: Tag Cloud & Delete Confirm
       ========================================================================== */
    document.querySelectorAll('.kcan-submitdelete').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            var confirmMsg = (typeof kcanData !== 'undefined' && kcanData.confirmDeleteWiki) ? kcanData.confirmDeleteWiki : 'Are you sure?';
            if (!confirm(confirmMsg)) e.preventDefault();
        });
    });

    document.querySelectorAll('.kcan-tag-cloud-item').forEach(function(item) {
        item.addEventListener('click', function() {
            var tagText = this.innerText;
            var inputField = document.getElementById('kcan_wiki_tags_input');
            if (!inputField) return;
            var currentVal = inputField.value.trim();
            if (currentVal === '') {
                inputField.value = tagText;
            } else {
                var currentTags = currentVal.split(',').map(function(t) { return t.trim(); });
                if (currentTags.indexOf(tagText) === -1) {
                    inputField.value = currentVal + ', ' + tagText;
                }
            }
            var originalBg = this.style.background;
            var originalColor = this.style.color;
            this.style.background = '#2271b1';
            this.style.color = '#fff';
            setTimeout(() => { this.style.background = originalBg; this.style.color = originalColor; }, 200);
        });
    });
});