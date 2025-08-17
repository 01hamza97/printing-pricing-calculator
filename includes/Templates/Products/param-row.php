<?php
// expects $param (array), $data (form data). Do not change existing fields.
if (!isset($param)) return;

// Helper (object/array)
if (!function_exists('ppc_get_param')) {
    function ppc_get_param($params, $id, $key) {
        foreach ($params as $p) {
            if ((is_object($p) && $p->parameter_id == $id) || (is_array($p) && (($p['parameter_id'] ?? $p['id'] ?? null) == $id))) {
                return is_object($p) ? ($p->$key ?? null) : ($p[$key] ?? null);
            }
        }
        return null;
    }
}

$options = isset($param['options']) && is_array($param['options']) ? $param['options'] : [];
// Prefer full set from form.php; fallback to this param only
$allParamsSource = (isset($parameters) && is_array($parameters) && !empty($parameters)) ? $parameters : [$param];

// Normalized set for JS (id, title, options[id,title])
$clientParams = [];
foreach ($allParamsSource as $p0) {
    $clientParams[] = [
        'id'      => (int)($p0['id'] ?? $p0['parameter_id'] ?? 0),
        'title'   => $p0['title'] ?? '',
        'options' => array_map(function($o0){
            $title = $o0['title'] ?? ($o0['meta_value']['option'] ?? ($o0['option'] ?? ''));
            $id    = (int)($o0['id'] ?? 0);
            return ['id' => $id, 'title' => $title];
        }, $p0['options'] ?? [])
    ];
}
?>
<div class="ppc-param-row" data-param-id="<?php echo (int)($param['id'] ?? 0); ?>" style="margin-bottom: 16px; border: 1px solid #ddd; border-radius: 6px; overflow: hidden;">
    <!-- Keep header classes (toggle/sort/remove JS in form.php relies on these) -->
    <div class="ppc-param-header" style="display:flex;align-items:center;gap:8px;padding:10px;cursor:pointer;background:#fafafa;border-bottom:1px solid #eee;">
        <span class="ppc-param-drag" title="Drag to reorder" style="cursor:move;">⋮⋮</span>
        <strong style="flex:1;">
            <?php echo esc_html($param['title'] ?? ''); ?>
            <?php if (!empty($param['front_name'])): ?>
                <span style="color:#888;font-weight:normal;">(<?php echo esc_html($param['front_name']); ?>)</span>
            <?php endif; ?>
        </strong>
        <button type="button" class="button button-small ppc-param-remove">Remove</button>
        <span class="ppc-param-toggle" style="transition:transform .2s;">▾</span>
    </div>

    <div class="ppc-param-details" style="display:none; padding:12px;">
        <?php if (!empty($param['content'])): ?>
            <div style="margin-bottom:10px;color:#555;"><?php echo wp_kses_post($param['content']); ?></div>
        <?php endif; ?>

        <?php if (!empty($options)): ?>
            <table class="widefat striped" style="margin-bottom:16px;">
                <thead>
                    <tr>
                        <th style="width:30%;">Option</th>
                        <th style="width:15%;">Base Cost</th>
                        <th style="width:15%;">Override Price</th>
                        <th style="width:10%;">Select</th>
                        <th style="width:30%;">Conditions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($options as $opt): ?>
                        <?php
                        $opt_id   = (int)($opt['id'] ?? 0);
                        $opt_name = $opt['title'] ?? ($opt['meta_value']['option'] ?? ($opt['option'] ?? ''));
                        $baseCost = (float)($opt['meta_value']['cost'] ?? $opt['cost'] ?? 0);
                        // Keep EXISTING field names: override_prices[] and selected_options[]
                        $override_val = isset($data['override_prices'][$opt_id])
                            ? $data['override_prices'][$opt_id]
                            : ($opt['override_price'] ?? '');
                        ?>
                        <tr data-option-id="<?php echo $opt_id; ?>">
                            <td>
                                <?php echo esc_html($opt_name); ?>
                                <div style="color:#888;font-size:12px;margin-top:2px;">ID: <?php echo $opt_id; ?></div>
                            </td>
                            <td><?php echo number_format($baseCost, 2); ?></td>
                            <td>
                                <input type="number" step="0.01" name="override_prices[<?php echo $opt_id; ?>]"
                                       value="<?php echo esc_attr($override_val !== '' ? $override_val : $baseCost); ?>" class="small-text" />
                            </td>
                            <td>
                                <input type="checkbox" name="selected_options[]" <?php checked(isset($data['option_prices'][$opt_id])); ?> value="<?php echo esc_attr($opt_id); ?>" />
                            </td>
                            <td>
                                <button type="button"
                                        class="button button-small ppc-open-conditions"
                                        data-option-id="<?php echo $opt_id; ?>">
                                    Edit Conditions
                                </button>
                            </td>
                        </tr>
                        <!-- Hidden inline editor row for this option -->
                        <tr class="ppc-conditions-row" data-option-id="<?php echo $opt_id; ?>" style="display:none;background:#fcfcfc;">
                            <td colspan="999" style="padding:12px 10px;">
                                <div class="ppc-conditions-wrap" data-option-id="<?php echo $opt_id; ?>">
                                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                        <strong>Conditions for option: <?php echo esc_html($opt_name); ?></strong>
                                        <div>
                                            <button type="button" class="button button-secondary button-small ppc-add-group" data-option-id="<?php echo $opt_id; ?>">+ Add Group</button>
                                        </div>
                                    </div>
                                    <div class="ppc-condition-groups" data-option-id="<?php echo $opt_id; ?>"></div>
                                    <!-- New hidden input (does NOT affect existing fields) -->
                                    <input type="hidden"
                                        class="ppc-conditions-json"
                                        name="conditions[<?php echo $opt_id; ?>]"
                                        value="<?php echo esc_attr( wp_json_encode( $existing_option_conditions[ $opt_id ] ?? [] ) ); ?>" />
                                    <p style="margin:8px 0 0;color:#666;font-size:12px;">
                                        Each <b>Group</b> evaluates its rows with the selected operator (AND/OR).
                                        When this <i>option</i> is selected, matching groups will <i>apply action</i> to the chosen target (parameter or option).
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Parameter-level conditions (new; doesn’t affect existing fields) -->
        <div class="ppc-param-wide-conditions" style="margin-top:16px;border-top:1px solid #eee;padding-top:12px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <strong>Parameter-level Conditions (apply to the entire parameter)</strong>
                <div>
                    <button type="button" class="button button-secondary button-small ppc-add-param-group" data-param-id="<?php echo (int)($param['id'] ?? 0); ?>">+ Add Group</button>
                </div>
            </div>
            <div class="ppc-param-condition-groups" data-param-id="<?php echo (int)($param['id'] ?? 0); ?>"></div>
            <input type="hidden"
                class="ppc-param-conditions-json"
                name="param_conditions[<?php echo (int)($param['id'] ?? 0); ?>]"
                value="<?php echo esc_attr( wp_json_encode( $existing_param_conditions[ (int)($param['id'] ?? 0) ] ?? [] ) ); ?>" />
            <p style="margin:8px 0 0;color:#666;font-size:12px;">
                Use these rules when you want to hide/show targets based on this <b>parameter</b> (not a specific option).
                For example: “If Param A = ANY, hide Param B”.
            </p>
        </div>

        <!-- Keep original hidden fields for sorting/ids -->
        <input type="hidden" class="ppc-param-position" name="param_positions[]" value="<?php echo isset($param['position']) ? (int)$param['position'] : 0; ?>" />
        <!-- <input type="hidden" name="param_ids[]" value="<?php echo (int)($param['id'] ?? 0); ?>" /> -->
        <!-- <input type="hidden" name="param_positions[]" class="ppc-param-position" value="" /> -->
        <input type="hidden" name="parameters[]" value="<?php echo $param['id']; ?>" />
    </div>
</div>

<script>
(function(){
    // ===== Helpers =====
    function el(html){ var t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstChild; }
    function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

    function mergeUniqueById(base, add) {
        var map = {}; base.forEach(p => map[String(p.id)] = p);
        add.forEach(p => {
            var id = String(p.id);
            if (!map[id]) { map[id] = p; }
            else {
                var A = map[id].options || [], B = p.options || [];
                var oMap = {}; A.forEach(o => oMap[String(o.id)] = o);
                B.forEach(o => { oMap[String(o.id)] = o; });
                map[id].options = Object.values(oMap);
            }
        });
        return Object.values(map);
    }

    // Provide ALL parameters to a single global store; merge across rows
    var __THIS_PARAM_DATA__ = <?php echo wp_json_encode($clientParams); ?>;
    window.PPC_ALL_PARAMETERS = mergeUniqueById(window.PPC_ALL_PARAMETERS || [], __THIS_PARAM_DATA__);

    // Get currently selected params in the product UI (DOM) and exclude the current param
    function getSelectedParamsExcluding(currentParamId) {
        var result = [];
        var rows = document.querySelectorAll('#ppc-selected-params .ppc-param-row');
        rows.forEach(function(row){
            var pid = parseInt(row.getAttribute('data-param-id'), 10) || 0;
            if (!pid || pid === currentParamId) return; // exclude self
            var p = (window.PPC_ALL_PARAMETERS || []).find(function(pp){ return Number(pp.id) === pid; });
            if (p) result.push(p);
        });
        return result;
    }

    function buildParamOptionsHTML(currentParamId) {
        var list = getSelectedParamsExcluding(currentParamId);
        return list.map(function(p){
            var label = p.title || ('#'+p.id);
            return '<option value="'+p.id+'">'+escapeHtml(label)+'</option>';
        }).join('');
    }

    function groupTemplate(isParamGroup, groupIndex){
        var cls = isParamGroup ? 'ppc-param-condition-group' : 'ppc-condition-group';
        return el(
            '<div class="'+cls+'" data-group-index="'+groupIndex+'" style="border:1px solid #e5e5e5;border-radius:6px;margin-bottom:10px;padding:10px;">' +
                '<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">' +
                    '<strong style="flex:1">Group '+(groupIndex+1)+'</strong>' +
                    '<label style="display:inline-flex;align-items:center;gap:6px;">Operator ' +
                        '<select class="ppc-operator"><option value="AND">AND</option><option value="OR">OR</option></select>' +
                    '</label>' +
                    '<button type="button" class="button-link-delete ppc-remove-group">Remove group</button>' +
                '</div>' +
                '<div class="ppc-group-rows" style="display:flex;flex-direction:column;gap:6px;"></div>' +
                '<div><button type="button" class="button button-small ppc-add-row">+ Add Condition</button></div>' +
            '</div>'
        );
    }

    function rowTemplate(currentParamId){
        var paramOptions = buildParamOptionsHTML(currentParamId);
        return el(
            '<div class="ppc-group-row" data-row-index="0" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:8px;align-items:center;">' +
                '<label>Target Param<select class="ppc-target-param"><option value="">— select —</option>'+ paramOptions +'</select></label>' +
                '<label>Target Option<select class="ppc-target-option"><option value="">ANY</option></select></label>' +
                '<label>Action<select class="ppc-action"><option value="show">Show</option><option value="hide">Hide</option></select></label>' +
                '<div style="color:#666;font-size:12px;">Apply to selected target</div>' +
                '<button type="button" class="button-link-delete ppc-remove-row">Remove</button>' +
            '</div>'
        );
    }

    function populateTargetOptions(targetParamSelect){
        var row = targetParamSelect.closest('.ppc-group-row');
        var selParamId = String(targetParamSelect.value || '');
        var optSelect  = row.querySelector('.ppc-target-option');
        if (!optSelect) return;
        optSelect.innerHTML = '<option value="">ANY</option>';
        if (!selParamId) return;

        var p = (window.PPC_ALL_PARAMETERS || []).find(function(pp){ return String(pp.id) === selParamId; });
        if (p && Array.isArray(p.options)) {
            p.options.forEach(function(o){
                var op = document.createElement('option');
                op.value = o.id;
                op.textContent = (o.title || ('#'+o.id));
                optSelect.appendChild(op);
            });
        }
    }

    function renumberGroupsOption(wrap){
        wrap.querySelectorAll('.ppc-condition-group').forEach(function(g,i){
            g.setAttribute('data-group-index', i);
            var s = g.querySelector('strong'); if (s) s.textContent = 'Group ' + (i+1);
            renumberRows(g);
        });
    }
    function renumberGroupsParam(wrap){
        wrap.querySelectorAll('.ppc-param-condition-group').forEach(function(g,i){
            g.setAttribute('data-group-index', i);
            var s = g.querySelector('strong'); if (s) s.textContent = 'Group ' + (i+1);
            renumberRows(g);
        });
    }
    function renumberRows(groupEl){
        groupEl.querySelectorAll('.ppc-group-row').forEach(function(r,i){
            r.setAttribute('data-row-index', i);
        });
    }

    function serializeOptionConditions(wrap){
        if (!wrap) return;
        var groups = [];
        wrap.querySelectorAll('.ppc-condition-group').forEach(function(g){
            var operator = (g.querySelector('.ppc-operator')||{}).value || 'AND';
            var rows = [];
            g.querySelectorAll('.ppc-group-row').forEach(function(r){
                rows.push({
                    target_param_id:  (r.querySelector('.ppc-target-param')||{}).value || '',
                    target_option_id: (r.querySelector('.ppc-target-option')||{}).value || '',
                    action:           (r.querySelector('.ppc-action')||{}).value || 'show'
                });
            });
            groups.push({ operator: operator, rows: rows });
        });
        var input = wrap.querySelector('.ppc-conditions-json');
        if (input) input.value = JSON.stringify(groups);
    }

    function serializeParamConditions(wrap){
        if (!wrap) return;
        var groups = [];
        wrap.querySelectorAll('.ppc-param-condition-group').forEach(function(g){
            var operator = (g.querySelector('.ppc-operator')||{}).value || 'AND';
            var rows = [];
            g.querySelectorAll('.ppc-group-row').forEach(function(r){
                rows.push({
                    target_param_id:  (r.querySelector('.ppc-target-param')||{}).value || '',
                    target_option_id: (r.querySelector('.ppc-target-option')||{}).value || '',
                    action:           (r.querySelector('.ppc-action')||{}).value || 'show'
                });
            });
            groups.push({ operator: operator, rows: rows });
        });
        var input = wrap.querySelector('.ppc-param-conditions-json');
        if (input) input.value = JSON.stringify(groups);
    }

    // ---- Hydration helpers ----
    function hydrateOptionEditor(rowEl){
        if (!rowEl) return;
        var wrap = rowEl.querySelector('.ppc-conditions-wrap'); if (!wrap) return;
        if (wrap.__hydrated) return;

        var input = wrap.querySelector('.ppc-conditions-json');
        var groups = [];
        try { groups = JSON.parse(input.value || '[]'); } catch(e){ groups = []; }
        if (!groups.length) { wrap.__hydrated = true; return; }

        var groupsEl = wrap.querySelector('.ppc-condition-groups');
        groups.forEach(function(g, gi){
            var gEl = groupTemplate(false, gi);
            groupsEl.appendChild(gEl);
            var opSel = gEl.querySelector('.ppc-operator'); if (opSel) opSel.value = g.operator || 'AND';

            var rowsEl = gEl.querySelector('.ppc-group-rows');
            (g.rows || []).forEach(function(r){
                var container = gEl.closest('.ppc-param-row');
                var currentParamId = container ? (parseInt(container.getAttribute('data-param-id'),10)||0) : 0;
                var rEl = rowTemplate(currentParamId);
                rowsEl.appendChild(rEl);

                if (r.target_param_id) {
                    rEl.querySelector('.ppc-target-param').value = String(r.target_param_id);
                    populateTargetOptions(rEl.querySelector('.ppc-target-param'));
                }
                if (r.target_option_id) {
                    rEl.querySelector('.ppc-target-option').value = String(r.target_option_id);
                }
                if (r.action) {
                    rEl.querySelector('.ppc-action').value = r.action;
                }
            });
        });
        wrap.__hydrated = true;
    }

    function hydrateParamEditor(wrapP){
        if (!wrapP || wrapP.__hydrated) return;
        var input = wrapP.querySelector('.ppc-param-conditions-json'); if (!input) return;

        var groups = [];
        try { groups = JSON.parse(input.value || '[]'); } catch(e){ groups = []; }
        if (!groups.length) { wrapP.__hydrated = true; return; }

        var groupsEl = wrapP.querySelector('.ppc-param-condition-groups');
        groups.forEach(function(g, gi){
            var gEl = groupTemplate(true, gi);
            groupsEl.appendChild(gEl);
            var opSel = gEl.querySelector('.ppc-operator'); if (opSel) opSel.value = g.operator || 'AND';

            var rowsEl = gEl.querySelector('.ppc-group-rows');
            (g.rows || []).forEach(function(r){
                var container = gEl.closest('.ppc-param-row');
                var currentParamId = container ? (parseInt(container.getAttribute('data-param-id'),10)||0) : 0;
                var rEl = rowTemplate(currentParamId);
                rowsEl.appendChild(rEl);

                if (r.target_param_id) {
                    rEl.querySelector('.ppc-target-param').value = String(r.target_param_id);
                    populateTargetOptions(rEl.querySelector('.ppc-target-param'));
                }
                if (r.target_option_id) {
                    rEl.querySelector('.ppc-target-option').value = String(r.target_option_id);
                }
                if (r.action) {
                    rEl.querySelector('.ppc-action').value = r.action;
                }
            });
        });
        wrapP.__hydrated = true;
    }

    // ===== Global delegated listeners (bind once) =====
    if (!window.__PPC_CONDITIONS_BOUND__) {
        window.__PPC_CONDITIONS_BOUND__ = true;

        document.addEventListener('click', function(e){
            // Open/close option-level editor (and hydrate)
            if (e.target.classList.contains('ppc-open-conditions')) {
                var tr = e.target.closest('tr'); if (!tr) return;
                var optId = e.target.getAttribute('data-option-id');
                var table = tr.closest('table'); if (!table) return;
                var row = table.querySelector('.ppc-conditions-row[data-option-id="'+optId+'"]');
                if (!row) return;
                var toShow = (row.style.display === 'none' || !row.style.display);
                row.style.display = toShow ? '' : 'none';
                if (toShow) hydrateOptionEditor(row);
            }

            // Add option-level group
            if (e.target.classList.contains('ppc-add-group')) {
                var wrap = e.target.closest('.ppc-conditions-wrap'); if (!wrap) return;
                var groups = wrap.querySelector('.ppc-condition-groups');
                var gi = groups.children.length;
                var gEl = groupTemplate(false, gi);
                groups.appendChild(gEl);
                serializeOptionConditions(wrap);
            }

            // Add parameter-level group
            if (e.target.classList.contains('ppc-add-param-group')) {
                var wrapP = e.target.closest('.ppc-param-wide-conditions'); if (!wrapP) return;
                // Ensure existing saved data is visible before adding new (hydrates once)
                hydrateParamEditor(wrapP);

                var groupsP = wrapP.querySelector('.ppc-param-condition-groups');
                var giP = groupsP.children.length;
                var gElP = groupTemplate(true, giP);
                groupsP.appendChild(gElP);
                serializeParamConditions(wrapP);
            }

            // Remove group
            if (e.target.classList.contains('ppc-remove-group')) {
                var grp = e.target.closest('.ppc-condition-group, .ppc-param-condition-group'); if (!grp) return;
                var isParam = grp.classList.contains('ppc-param-condition-group');
                var wrap = isParam ? grp.closest('.ppc-param-wide-conditions') : grp.closest('.ppc-conditions-wrap');
                grp.remove();
                if (isParam) { renumberGroupsParam(wrap); serializeParamConditions(wrap); }
                else { renumberGroupsOption(wrap); serializeOptionConditions(wrap); }
            }

            // Add row (both kinds)
            if (e.target.classList.contains('ppc-add-row')) {
                var grp  = e.target.closest('.ppc-condition-group, .ppc-param-condition-group'); if (!grp) return;
                var rows = grp.querySelector('.ppc-group-rows');
                var ri   = rows.children.length;

                var container = grp.closest('.ppc-param-row');
                var currentParamId = container ? (parseInt(container.getAttribute('data-param-id'), 10) || 0) : 0;

                var rEl  = rowTemplate(currentParamId);
                rEl.setAttribute('data-row-index', ri);
                rows.appendChild(rEl);

                var tps = rEl.querySelector('.ppc-target-param');
                if (tps) populateTargetOptions(tps);

                var wrap = grp.closest('.ppc-conditions-wrap, .ppc-param-wide-conditions');
                if (wrap.classList.contains('ppc-param-wide-conditions')) serializeParamConditions(wrap);
                else serializeOptionConditions(wrap);
            }

            // Remove row
            if (e.target.classList.contains('ppc-remove-row')) {
                var grp2 = e.target.closest('.ppc-condition-group, .ppc-param-condition-group'); if (!grp2) return;
                var row2 = e.target.closest('.ppc-group-row'); if (!row2) return;
                row2.remove();
                var wrap2 = grp2.closest('.ppc-conditions-wrap, .ppc-param-wide-conditions');
                renumberRows(grp2);
                if (wrap2.classList.contains('ppc-param-wide-conditions')) serializeParamConditions(wrap2);
                else serializeOptionConditions(wrap2);
            }
        });

        document.addEventListener('change', function(e){
            var cls = e.target.classList;
            if (cls.contains('ppc-target-param')) {
                populateTargetOptions(e.target);
                var wrap = e.target.closest('.ppc-conditions-wrap, .ppc-param-wide-conditions'); if (!wrap) return;
                if (wrap.classList.contains('ppc-param-wide-conditions')) serializeParamConditions(wrap);
                else serializeOptionConditions(wrap);
            }
            if (cls.contains('ppc-target-option') || cls.contains('ppc-action') || cls.contains('ppc-operator')) {
                var wrap = e.target.closest('.ppc-conditions-wrap, .ppc-param-wide-conditions'); if (!wrap) return;
                if (wrap.classList.contains('ppc-param-wide-conditions')) serializeParamConditions(wrap);
                else serializeOptionConditions(wrap);
            }
        });

        // AUTO-HYDRATE parameter-level editors once on load (so saved groups are visible even before any click)
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.ppc-param-wide-conditions').forEach(function(wrapP){
                hydrateParamEditor(wrapP);
            });
        });
    }
})();
</script>
