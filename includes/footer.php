    <!--==================== FOOTER ====================-->
    <footer class="footer">
        <div class="footer__container container grid">
            <div>
                <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>index.php" class="footer__logo">
                    <img src="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>assets/img/evsu-logo.png">EVSU VOICE
                </a>

                <p class="footer__description">
                    Your Voice. <br>
                    Your Campus. <br>
                    Your EVSU VOICE.
                </p>
            </div>

            <div class="footer__data grid">
                <div>
                    <h3 class="footer__title">About</h3>

                    <ul class="footer__links">
                        <li>
                            <a href="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>browse-suggestions.php" class="footer__link">Suggestions</a>
                        </li>

                        <li>
                            <a href="#" class="footer__link">FAQs</a>
                        </li>

                        <li>
                            <a href="#" class="footer__link">Privacy Policy</a>
                        </li>

                        <li>
                            <a href="#" class="footer__link">Terms of Services</a>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="footer__title">Contact</h3>

                    <ul class="footer__links">
                        <li>
                            <address class="footer__info">
                                Brgy. Don Felipe Larrazabal, <br>
                                Ormoc City, Leyte, <br>
                                Philippines, 6541
                            </address>
                        </li>

                        <li>
                            <address class="footer__info">
                                jetvenson.romero@evsu.edu.ph <br>
                                +63 966 254 3798
                            </address>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="footer__title">Social</h3>

                    <div class="footer__social">
                        <a href="https://www.facebook.com/R0m3r0Jet" target="_blank" class="footer__social-link">
                            <i class="ri-facebook-circle-line"></i>
                        </a>

                        <a href="https://www.instagram.com/" target="_blank" class="footer__social-link">
                            <i class="ri-instagram-line"></i>
                        </a>

                        <a href="https://twitter.com/" target="_blank" class="footer__social-link">
                            <i class="ri-twitter-x-line"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <span class="footer__copy">
            &#169; 2025 Eastern Visayas State University. All Rights Reserved.
        </span>
    </footer>

    <!--========== SCROLL UP ==========-->
    <a href="#" class="scrollup" id="scroll-up">
        <i class="ri-arrow-up-line"></i>
    </a>

    <!--=============== SCROLLREVEAL ===============-->
    <script src="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>assets/js/scrollreveal.min.js"></script>

    <!--=============== SWIPER JS ===============-->
    <script src="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>assets/js/swiper-bundle.min.js"></script>

    <!--=============== MAIN JS ===============-->
    <script src="<?php echo strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../' : ''; ?>assets/js/main.js"></script>
    </body>

    </html>