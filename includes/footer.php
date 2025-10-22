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
            </ul>
        </div>
        <div>
            <h3>Visit</h3>
            <p>123 Innovation Drive<br>Digital City, 456789</p>
        </div>
    </div>
    <p class="footer-copy">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
</footer>
<div class="newsletter-modal" id="newsletterModal" role="dialog" aria-modal="true" aria-labelledby="newsletterTitle" hidden>
    <div class="modal-content">
        <button class="modal-close" type="button" data-close-modal aria-label="Close newsletter sign-up">&times;</button>
        <h2 id="newsletterTitle">Unlock 10% off your first order</h2>
        <p>Join our newsletter for exclusive drops, tips, and launch alerts.</p>
        <form id="newsletterForm" action="scripts/subscribe.php" method="post" novalidate>
            <label for="newsletterEmail">Email address</label>
            <input type="email" id="newsletterEmail" name="email" placeholder="you@example.com" required>

            <label for="devicePreference">Primary interest</label>
            <select id="devicePreference" name="preference" required>
                <option value="">Select a category</option>
                <option value="Laptops">Laptops</option>
                <option value="Components">Components</option>
                <option value="Peripherals">Peripherals</option>
                <option value="Smart Home">Smart Home</option>
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
    </div>
</div>
</body>
</html>
