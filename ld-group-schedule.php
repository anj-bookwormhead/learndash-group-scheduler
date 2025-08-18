<?php
final class pdalearning_LD_Group_Slots_4col {
    const BOX_ID        = 'pdalearning_ld_group_schedule';
    const NONCE_ID      = 'pdalearning_ld_group_schedule_nonce';
    const NONCE_K       = 'pdalearning_ld_group_schedule_save';

    // NEW: constants for the Main Group Code box
    const CODE_BOX_ID   = 'pdalearning_ld_group_code';
    const CODE_META_KEY = '_ld_group_main_code';
    const CODE_FIELD    = 'ld_group_main_code';

    public static function init() {
        add_action('add_meta_boxes',        [__CLASS__, 'add_boxes']);
        add_action('save_post_groups',      [__CLASS__, 'save'], 10, 1);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('admin_head',            [__CLASS__, 'admin_css']);
        add_action('admin_footer',          [__CLASS__, 'admin_js']);

        // OPTIONAL: show column in Groups list
        add_filter('manage_groups_posts_columns',            [__CLASS__, 'add_admin_col']);
        add_action('manage_groups_posts_custom_column',      [__CLASS__, 'fill_admin_col'], 10, 2);
    }

    /** Register BOTH meta boxes */
    public static function add_boxes() {
        // Assigned Schedule (wide / main column)
        add_meta_box(
            self::BOX_ID,
            __('Assigned Schedule', 'pdalearning'),
            [__CLASS__, 'render_schedule_box'],
            'groups',
            'normal',
            'high'
        );

        // Main Group Code (compact; put it in the side column – change to 'normal' if you want it wide)
        add_meta_box(
            self::CODE_BOX_ID,
            __('Main Group Code', 'pdalearning'),
            [__CLASS__, 'render_code_box'],
            'groups',
            'side',
            'high'
        );
    }

    /** Render: Assigned Schedule (your existing box) */
    public static function render_schedule_box($post) {
        wp_nonce_field(self::NONCE_K, self::NONCE_ID);

        $slots = get_post_meta($post->ID, '_ld_group_slots', true);
        if (!is_array($slots) || empty($slots)) {
            $slots = [ ['date'=>'', 'venue'=>'', 'time'=>'', 'course'=>''] ];
        }

        // Fetch LearnDash courses for dropdown
        $courses = get_posts([
            'post_type'      => 'sfwd-courses',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC'
        ]);
        ?>
        <div id="pdalearning-slots-wrapper">
            <?php foreach ($slots as $i => $slot): ?>
                <div class="pdalearning-slot">
                    <div class="pdalearning-field">
                        <label for="pdalearning-date-<?php echo $i; ?>">Available Date</label>
                        <input type="text"
                               id="pdalearning-date-<?php echo $i; ?>"
                               class="regular-text pdalearning-date"
                               name="ld_group_slots[<?php echo $i; ?>][date]"
                               value="<?php echo esc_attr($slot['date']); ?>"
                               placeholder="e.g. August 15, 2025">
                    </div>
                    <div class="pdalearning-field">
                        <label for="pdalearning-venue-<?php echo $i; ?>">Venue</label>
                        <input type="text"
                               id="pdalearning-venue-<?php echo $i; ?>"
                               class="regular-text"
                               name="ld_group_slots[<?php echo $i; ?>][venue]"
                               value="<?php echo esc_attr($slot['venue']); ?>"
                               placeholder="Enter venue">
                    </div>
                    <div class="pdalearning-field">
                        <label for="pdalearning-time-<?php echo $i; ?>">Time</label>
                        <input type="time"
                               id="pdalearning-time-<?php echo $i; ?>"
                               class="regular-text pdalearning-time"
                               name="ld_group_slots[<?php echo $i; ?>][time]"
                               value="<?php echo esc_attr($slot['time']); ?>"
                               step="900"
                               placeholder="09:00">
                    </div>
                    <div class="pdalearning-field">
                        <label for="pdalearning-course-<?php echo $i; ?>">Assigned Course</label>
                        <select id="pdalearning-course-<?php echo $i; ?>"
                                name="ld_group_slots[<?php echo $i; ?>][course]"
                                class="regular-text">
                            <option value="">— Select Course —</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo esc_attr($c->ID); ?>"
                                    <?php selected($slot['course'] ?? '', $c->ID); ?>>
                                    <?php echo esc_html($c->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pdalearning-actions">
                        <button type="button" class="button pdalearning-remove-slot">Remove</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p><button type="button" class="button button-primary" id="pdalearning-add-slot">Add Slot</button></p>
        <?php
    }

    /** NEW: Render the Main Group Code (single input) */
    public static function render_code_box($post) {
        // reuse the same nonce so 1 save covers both boxes
        wp_nonce_field(self::NONCE_K, self::NONCE_ID);

        $code = get_post_meta($post->ID, self::CODE_META_KEY, true);
        ?>
        <p>
            <label for="<?php echo esc_attr(self::CODE_FIELD); ?>" class="screen-reader-text">
                <?php esc_html_e('Main Group Code', 'pdalearning'); ?>
            </label>
            <input type="text"
                   id="<?php echo esc_attr(self::CODE_FIELD); ?>"
                   name="<?php echo esc_attr(self::CODE_FIELD); ?>"
                   value="<?php echo esc_attr($code); ?>"
                   class="widefat"
                   placeholder="Enter the main group code">
        </p>
        <?php
    }

    /** Save BOTH meta boxes */
    public static function save($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST[self::NONCE_ID]) || !wp_verify_nonce($_POST[self::NONCE_ID], self::NONCE_K)) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Save slots (existing)
        if (isset($_POST['ld_group_slots']) && is_array($_POST['ld_group_slots'])) {
            $clean = [];
            foreach ($_POST['ld_group_slots'] as $slot) {
                $clean[] = [
                    'date'   => sanitize_text_field($slot['date'] ?? ''),
                    'venue'  => sanitize_text_field($slot['venue'] ?? ''),
                    'time'   => sanitize_text_field($slot['time'] ?? ''),
                    'course' => sanitize_text_field($slot['course'] ?? ''),
                ];
            }
            update_post_meta($post_id, '_ld_group_slots', $clean);
        } else {
            delete_post_meta($post_id, '_ld_group_slots');
        }

        // Save main group code (NEW)
        if (isset($_POST[self::CODE_FIELD])) {
            $code = sanitize_text_field(wp_unslash($_POST[self::CODE_FIELD]));
            if ($code !== '') {
                update_post_meta($post_id, self::CODE_META_KEY, $code);
            } else {
                delete_post_meta($post_id, self::CODE_META_KEY);
            }
        }
    }

    /** Enqueue datepicker for the schedule box */
    public static function enqueue($hook) {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'groups') return;
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-smoothness',
            'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css', [], '1.13.2');
    }

    public static function admin_css() {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'groups') return; ?>
        <style>
            /* 4-col row per slot: Date | Venue | Time | Course | Remove */
            #<?php echo esc_html(self::BOX_ID); ?> .pdalearning-slot{
                display:grid;
                grid-template-columns: 1fr 1fr 0.6fr 1fr auto;
                gap:12px;
                align-items:end;
                background:#fff;
                border:1px solid #ddd;
                padding:12px;
                margin-bottom:10px;
            }
            #<?php echo esc_html(self::BOX_ID); ?> .pdalearning-field label{
                display:block; font-weight:600; margin-bottom:4px;
            }
            #<?php echo esc_html(self::BOX_ID); ?> .pdalearning-field .regular-text,
            #<?php echo esc_html(self::BOX_ID); ?> .pdalearning-field select{
                width:100%; max-width:100%;
            }
            #<?php echo esc_html(self::BOX_ID); ?> .pdalearning-actions{
                display:flex; align-items:flex-end; padding-bottom:2px;
            }
            @media (max-width: 1100px){
                #<?php echo esc_html(self::BOX_ID); ?> .pdalearning-slot{
                    grid-template-columns: 1fr 1fr;
                }
                #<?php echo esc_html(self::BOX_ID); ?> .pdalearning-actions{
                    grid-column: 1 / -1;
                }
            }
        </style>
        <?php
    }

    public static function admin_js() {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'groups') return;

        // Fetch courses for JS template
        $courses = get_posts([
            'post_type'      => 'sfwd-courses',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC'
        ]);
        $courseOptions = '<option value="">— Select Course —</option>';
        foreach ($courses as $c) {
            $courseOptions .= '<option value="'.esc_attr($c->ID).'">'.esc_html($c->post_title).'</option>';
        }
        ?>
        <script>
        jQuery(function($){
            var $wrap = $('#pdalearning-slots-wrapper');
            var idx = $wrap.find('.pdalearning-slot').length;

            function initDatepickers(ctx){
                $(ctx).find('.pdalearning-date').datepicker({
                    dateFormat: 'MM d, yy'
                });
            }
            initDatepickers(document);

            $('#pdalearning-add-slot').on('click', function(){
                var i = idx++;
                var html = `
                <div class="pdalearning-slot">
                    <div class="pdalearning-field">
                        <label for="pdalearning-date-${i}">Available Date</label>
                        <input type="text" id="pdalearning-date-${i}" class="regular-text pdalearning-date"
                               name="ld_group_slots[${i}][date]" placeholder="e.g. August 15, 2025">
                    </div>
                    <div class="pdalearning-field">
                        <label for="pdalearning-venue-${i}">Venue</label>
                        <input type="text" id="pdalearning-venue-${i}" class="regular-text"
                               name="ld_group_slots[${i}][venue]" placeholder="Enter venue">
                    </div>
                    <div class="pdalearning-field">
                        <label for="pdalearning-time-${i}">Time</label>
                        <input type="time" id="pdalearning-time-${i}" class="regular-text pdalearning-time"
                               name="ld_group_slots[${i}][time]" step="900" placeholder="09:00">
                    </div>
                    <div class="pdalearning-field">
                        <label for="pdalearning-course-${i}">Assigned Course</label>
                        <select id="pdalearning-course-${i}" name="ld_group_slots[${i}][course]">
                            <?php echo $courseOptions; ?>
                        </select>
                    </div>
                    <div class="pdalearning-actions">
                        <button type="button" class="button pdalearning-remove-slot">Remove</button>
                    </div>
                </div>`;
                var $new = $(html).appendTo($wrap);
                initDatepickers($new);
            });

            $wrap.on('click', '.pdalearning-remove-slot', function(){
                $(this).closest('.pdalearning-slot').remove();
            });
        });
        </script>
        <?php
    }

    /* ===== Admin list column for Main Group Code (optional) ===== */
    public static function add_admin_col($cols){
        $cols['pdalearning_main_code'] = __('Main Group Code', 'pdalearning');
        return $cols;
    }
    public static function fill_admin_col($col, $post_id){
        if ($col === 'pdalearning_main_code') {
            $code = get_post_meta($post_id, self::CODE_META_KEY, true);
            echo esc_html($code);
        }
    }
}
pdalearning_LD_Group_Slots_4col::init();
