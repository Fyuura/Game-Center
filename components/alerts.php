<?php
function render_alert($errors = [], $success = null) {
    if (!empty($errors)) {
        echo '<div class="errors"><ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error, ENT_QUOTES) . '</li>';
        }
        echo '</ul></div>';
    } elseif (!empty($success)) {
        echo '<div class="success"><ul>';
        echo '<li>' . htmlspecialchars($success, ENT_QUOTES) . '</li>';
        echo '</ul></div>';
    }
}