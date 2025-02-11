<?php

function smalk_augment_robots_txt() {
    $robots_txt = smalk_get_robots_txt();

    smalk_echo_robots_txt_block($robots_txt);
}

add_action('do_robots', 'smalk_augment_robots_txt');

// Helpers

function smalk_echo_robots_txt_block($robots_txt) {
    echo "\n
# START SMALK AI ANALYTICS BLOCK
# ---------------------------\n";

    echo esc_textarea($robots_txt);

    echo "\n# ---------------------------
# END SMALK AI ANALYTICS BLOCK\n\n";
}