<?php
// This file expects $param (array), $data (form data)
if (!isset($param)) return;
if(!function_exists('ppc_get_param')) {
  function ppc_get_param($params, $id, $key) {
      foreach ($params as $p) {
          if ((is_object($p) && $p->parameter_id == $id) || (is_array($p) && $p['parameter_id'] == $id)) {
              return is_object($p) ? $p->$key : $p[$key];
          }
      }
      return null;
  }
}
?>
<div class="ppc-param-row" data-param-id="<?php echo $param['id']; ?>" style="margin-bottom: 20px; border: 1px solid #ddd; padding: 10px;">
    <input type="hidden" name="parameters[]" value="<?php echo $param['id']; ?>" />
    <label><strong><?php echo esc_html($param['title']); ?></strong> (<?php echo esc_html($param['front_name']); ?>)</label>
    <label style="margin-left:12px;">
        <input
            type="checkbox"
            name="is_required[<?php echo esc_attr($param['id']); ?>]"
            value="1"
            <?php if (isset($data['params'])) checked(ppc_get_param($data['params'], $param['id'], 'is_required') == 1); ?>
        /> Required
    </label>
    <button type="button" class="button-link ppc-param-remove" style="color:red; margin-left:18px;">Remove</button>
    <?php if (!empty($param['options'])): ?>
        <table class="widefat fixed striped" style="margin-top:10px;">
            <thead>
                <tr>
                    <th>Option</th>
                    <th>Slug</th>
                    <th>Image</th>
                    <th>Base Cost</th>
                    <th>Override Price</th>
                    <th>Enable</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($param['options'] as $opt): ?>
                <tr>
                    <td><?php echo esc_html($opt['title']); ?></td>
                    <td><?php echo esc_html($opt['slug']); ?></td>
                    <td>
                        <?php if (!empty($opt['image'])): ?>
                            <img src="<?php echo esc_url($opt['image']); ?>" style="width: 50px; height: auto;" />
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($opt['cost']); ?></td>
                    <td>
                        <input type="number" step="0.01" name="override_prices[<?php echo esc_attr($opt['id']); ?>]" value="<?php echo esc_attr($data['option_prices'][$opt['id']] ?? $opt['cost']); ?>" class="small-text" />
                    </td>
                    <td>
                        <input type="checkbox" name="selected_options[]" value="<?php echo esc_attr($opt['id']); ?>" <?php checked(isset($data['option_prices'][$opt['id']])); ?> />
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
