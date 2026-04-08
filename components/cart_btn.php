<?php
function renderCartButton($cartCount = 0) {
    // HTML çıktısı
    ?>
    <div class="cart-container">
        <a href="pages/cart.php" class="cart-btn">
            <i class="fa fa-shopping-cart"></i>
            <?php if ($cartCount > 0): ?>
                <span class="cart-count"><?php echo $cartCount; ?></span>
            <?php endif; ?>
        </a>
    </div>
    <?php
}