<?php
$page_title = "Home";
include 'includes/header.php';
?>

<!--==================== HOME ====================-->
<section class="home section" id="home">
    <div class="home__container container grid">
        <div class="home__data">
            <h1 class="home__title">
                EVSU VOICE
            </h1>

            <p class="home__description">
                Empowers the EVSU community to share insights, ideas, and feedback—shaping a better campus, together.
            </p>

            <a href="browse-suggestions.php" class="button">Explore Suggestions</a>
        </div>

        <div class="home__images">
            <div class="home__swiper swiper">
                <div class="swiper-wrapper">
                    <article class="home__article swiper-slide">
                        <img src="assets/img/image-1.png" alt="image" class="home__img">
                    </article>

                    <article class="home__article swiper-slide">
                        <img src="assets/img/image-2.png" alt="image" class="home__img">
                    </article>

                    <article class="home__article swiper-slide">
                        <img src="assets/img/image-3.png" alt="image" class="home__img">
                    </article>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>