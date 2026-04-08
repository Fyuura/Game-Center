<?php

function renderBalanceButton($balance = 0.00) {
    $formatted_balance = number_format($balance, 2, ',', '.') . ' $';
    ?>
    <div class="balance-container">
        <a href="pages/user_wallet.php" class="balance-btn">
            <span class="balance-amount"><?php echo $formatted_balance; ?></span>
        </a>
    </div>
    <?php
}

?>