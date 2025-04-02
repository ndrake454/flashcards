</div><!-- End container -->
    
    <footer class="py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <h5><?php echo APP_NAME; ?></h5>
                    <p class="mb-0">Enhance your learning with AI-powered flashcards</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                    <div class="mt-2">
                        <a href="#" class="me-3" title="Privacy Policy">Privacy Policy</a>
                        <a href="#" class="me-3" title="Terms of Service">Terms of Service</a>
                        <a href="#" title="Contact Us">Contact Us</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Accessibility Controls -->
    <div id="accessibility-controls" class="d-none">
        <button id="increase-font" class="btn btn-sm" title="Increase Font Size">A+</button>
        <button id="decrease-font" class="btn btn-sm" title="Decrease Font Size">A-</button>
        <button id="toggle-high-contrast" class="btn btn-sm" title="Toggle High Contrast">High Contrast</button>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Theme Switcher -->
    <script src="<?php echo APP_URL; ?>/assets/js/themes.js"></script>
    
    <!-- Accessibility JS -->
    <script src="<?php echo APP_URL; ?>/assets/js/accessibility.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    
    <!-- Cross-browser compatibility polyfills -->
    <script>
        // Polyfill for CustomEvent in IE
        (function() {
            if (typeof window.CustomEvent === "function") return false;
            
            function CustomEvent(event, params) {
                params = params || { bubbles: false, cancelable: false, detail: null };
                var evt = document.createEvent('CustomEvent');
                evt.initCustomEvent(event, params.bubbles, params.cancelable, params.detail);
                return evt;
            }
            
            window.CustomEvent = CustomEvent;
        })();
        
        // Check for service worker support and register if available
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?php echo APP_URL; ?>/service-worker.js').then(function(registration) {
                    console.log('ServiceWorker registration successful');
                }).catch(function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
    
    <!-- Page-specific JS -->
    <?php if(isset($extraJS)): ?>
        <?php foreach($extraJS as $js): ?>
            <script src="<?php echo APP_URL; ?>/assets/js/<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>