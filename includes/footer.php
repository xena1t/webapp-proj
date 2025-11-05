        </main>
        <footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <h3>About TechMart</h3>
            <p>We curate the latest in premium consumer electronics and IT gear, backed by expert support and fast delivery.</p>
        </div>
        <div>
            <h3>Need Help?</h3>
            <ul>
                <li><a href="mailto:support@techmart.local">support@techmart.local</a></li>
                <li><a href="order-status.php">Track your order</a></li>
                <li><a href="checkout.php">Shipping & Returns</a></li>
                <li><a href="contact.php">Contact support</a></li>
            </ul>
        </div>
        <div>
            <h3>Visit</h3>
            <p>123 Innovation Drive<br>Digital City, 456789</p>
            <p><a href="about.php">About TechMart</a></p>
        </div>
    </div>
    <p class="footer-copy">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
        </footer>
    </div>
</div>
<?php $isLoggedIn = is_user_logged_in(); ?>
<?php $authenticatedUser = $isLoggedIn ? get_authenticated_user() : null; ?>
<div class="newsletter-modal" id="newsletterModal" role="dialog" aria-modal="true" aria-labelledby="newsletterTitle" hidden>
    <div class="modal-content">
        <button class="modal-close" type="button" data-close-modal aria-label="Close newsletter sign-up">&times;</button>
        <h2 id="newsletterTitle">Unlock 10% off your first order</h2>
        <p>Join our newsletter for exclusive drops, tips, and launch alerts.</p>

        <?php if (!$isLoggedIn): ?>
            <p class="modal-text">Sign in or create an account to subscribe and claim your welcome discount.</p>
            <div class="modal-actions">
                <a class="btn-primary" href="login.php">Sign in</a>
                <a class="btn-secondary" href="register.php">Create an account</a>
            </div>
        <?php else: ?>
            <form id="newsletterForm" action="scripts/subscribe.php" method="post" novalidate>
                <label for="newsletterEmail">Email address</label>
                <input type="email" id="newsletterEmail" name="email"
                    value="<?= htmlspecialchars($authenticatedUser['email'] ?? '') ?>" readonly required>

                <?php
                $newsletterCategories = [];
                if (isset($categories) && is_array($categories) && !empty($categories)) {
                    $newsletterCategories = $categories;
                } elseif (function_exists('fetch_categories')) {
                    try {
                        $newsletterCategories = fetch_categories();
                    } catch (Throwable $ignored) {
                        $newsletterCategories = [];
                    }
                }
                ?>
                <label for="devicePreference">Primary interest</label>
                <select id="devicePreference" name="preference" required>
                    <option value="">Select a category</option>
                    <?php foreach ($newsletterCategories as $categoryOption): ?>
                        <option value="<?= htmlspecialchars($categoryOption) ?>">
                            <?= htmlspecialchars($categoryOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <fieldset>
                    <legend>Budget focus</legend>
                    <label><input type="radio" name="budget" value="Value" required> Value</label>
                    <label><input type="radio" name="budget" value="Mid-range"> Mid-range</label>
                    <label><input type="radio" name="budget" value="Premium"> Premium</label>
                </fieldset>

                <label class="checkbox">
                    <input type="checkbox" name="terms" value="1" required>
                    I agree to receive communications from TechMart.
                </label>

                <button type="submit" class="btn-primary">Join &amp; Claim 10% Off</button>
                <p class="form-feedback" role="alert" hidden></p>
            </form>
        <?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggles = Array.prototype.slice.call(document.querySelectorAll('.manage-product-toggle'));
    if (!toggles.length) {
        return;
    }

    var OPEN_CLASS = 'is-open';
    var ACTIVE_CLASS = 'is-active';
    var toggleMap = {};
    var rowMap = {};

    function setToggleState(toggle, expanded) {
        if (!toggle) {
            return;
        }
        var openLabel = toggle.dataset.openLabel || 'Manage product';
        var closeLabel = toggle.dataset.closeLabel || 'Hide editor';
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.classList.toggle(ACTIVE_CLASS, expanded);
        toggle.textContent = expanded ? closeLabel : openLabel;
    }

    function closeAll() {
        Object.keys(rowMap).forEach(function (rowId) {
            var row = rowMap[rowId];
            var toggle = toggleMap[rowId];
            if (!row || !toggle) {
                return;
            }
            row.classList.remove(OPEN_CLASS);
            setToggleState(toggle, false);
        });
    }

    function updateHistory(productId, anchorId) {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }
        var params = new URLSearchParams(window.location.search);
        if (productId) {
            params.set('manage', productId);
        } else {
            params.delete('manage');
        }
        var newUrl = window.location.pathname;
        var searchString = params.toString();
        if (searchString) {
            newUrl += '?' + searchString;
        }
        if (productId && anchorId) {
            newUrl += '#' + anchorId;
        }
        window.history.replaceState(null, '', newUrl);
    }

    toggles.forEach(function (toggle) {
        var rowId = toggle.getAttribute('aria-controls');
        if (!rowId) {
            return;
        }
        var row = document.getElementById(rowId);
        if (!row) {
            return;
        }
        toggleMap[rowId] = toggle;
        rowMap[rowId] = row;

        // Normalise initial state in case PHP rendered an open row (e.g., validation errors or deep links).
        setToggleState(toggle, row.classList.contains(OPEN_CLASS));

        toggle.addEventListener('click', function (event) {
            // Allow modified clicks to keep default browser behaviour (new tab, etc.).
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }
            event.preventDefault();

            var targetRow = rowMap[rowId];
            if (!targetRow) {
                return;
            }

            var isAlreadyOpen = targetRow.classList.contains(OPEN_CLASS);

            closeAll();

            if (!isAlreadyOpen) {
                targetRow.classList.add(OPEN_CLASS);
                setToggleState(toggle, true);

                var productId = toggle.dataset.productId || '';
                var anchorId = toggle.dataset.anchor || '';
                updateHistory(productId, anchorId);

                if (typeof targetRow.scrollIntoView === 'function') {
                    targetRow.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
                }
            } else {
                updateHistory('', '');
            }
        });
    });
});
</script>
</body>
</html>
