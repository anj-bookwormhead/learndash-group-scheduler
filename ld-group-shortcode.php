<?php

// [ld_group_schedules groups="38,42" show="date,venue,time,course" future_only="false" title_tag="h2"]
add_shortcode('ld_group_schedules', function($atts){
    $a = shortcode_atts([
        'groups'      => '',                         // comma-separated IDs; empty = all published groups
        'show'        => 'date,venue,time,course',
        'future_only' => 'false',
        'title_tag'   => 'h2',
        'limit'       => '-1',
        'orderby'     => 'title',
        'order'       => 'ASC',
    ], $atts, 'ld_group_schedules');

    $future_only = filter_var($a['future_only'], FILTER_VALIDATE_BOOLEAN);
    $show_fields = array_map('trim', explode(',', strtolower($a['show'])));
    $title_tag   = preg_replace('/[^a-z0-9]/i','', $a['title_tag']); if ($title_tag==='') $title_tag='h2';

    // Decide which groups to query
    $args = [
        'post_type'      => 'groups',
        'posts_per_page' => intval($a['limit']),
        'orderby'        => sanitize_key($a['orderby']),
        'order'          => in_array(strtoupper($a['order']), ['ASC','DESC'], true) ? strtoupper($a['order']) : 'ASC',
        'post_status'    => 'publish',
    ];
    $ids_filter = array_filter(array_map('absint', explode(',', $a['groups'])));
    if (!empty($ids_filter)) {
        $args['post__in'] = $ids_filter;
        $args['orderby'] = 'post__in';
    }

    $q = new WP_Query($args);
    if (!$q->have_posts()) return '';

    $out = '<div class="a925-group-schedules">';
    while ($q->have_posts()) { $q->the_post();
        $gid   = get_the_ID();
        $slots = get_post_meta($gid, '_ld_group_slots', true);
        if (!is_array($slots) || empty($slots)) continue;

        // Future-only filter
        if ($future_only) {
            $now = current_time('timestamp');
            $slots = array_filter($slots, function($s) use ($now){
                $t = isset($s['date']) ? strtotime($s['date']) : false;
                return $t ? ($t >= $now) : true;
            });
            if (empty($slots)) continue;
        }

        $out .= sprintf('<%1$s class="a925-group-title">%2$s</%1$s>', $title_tag, esc_html(get_the_title($gid)));
        $out .= '<ul class="a925-schedule-list">';
        foreach ($slots as $s) {
            $parts = [];
            if (in_array('date', $show_fields, true)  && !empty($s['date']))   $parts[]  = esc_html($s['date']);
            if (in_array('venue', $show_fields, true) && !empty($s['venue']))  $parts[]  = 'Venue: ' . esc_html($s['venue']);
            if (in_array('time', $show_fields, true)  && !empty($s['time']))   $parts[]  = 'Time: '  . esc_html($s['time']);
            if (in_array('course', $show_fields, true) && !empty($s['course'])){
                $c_id = absint($s['course']);
                $label = $c_id ? get_the_title($c_id) : $s['course'];
                $url   = $c_id ? get_permalink($c_id) : '';
                $parts[] = 'Course: ' . ($url ? '<a href="'.esc_url($url).'">'.esc_html($label).'</a>' : esc_html($label));
            }
            if (!empty($parts)) $out .= '<li>' . implode(' · ', $parts) . '</li>';
        }
        $out .= '</ul>';
    }
    wp_reset_postdata();
    $out .= '</div>';

    return $out;
});

// Optional minimal styles
add_action('wp_head', function(){
    echo '<style>
        .a925-group-schedules .a925-group-title,
        .a925-group-schedule .a925-group-title { margin: .5em 0 .25em; }
        .a925-schedule-list { margin: 0 0 1.25em 1.1em; }
        .a925-schedule-list li { margin: .15em 0; }
    </style>';
});
