/*=============== SEARCH ===============*/
const searchButton = document.getElementById('search-button'),
    searchClose = document.getElementById('search-close'),
    searchContent = document.getElementById('search-content')

/*=============== SEARCH SHOW ===============*/
/* Validate if constant exists */
if(searchButton) {
    searchButton.addEventListener('click', () => {
        searchContent.classList.add('show-search')
    })
}

/*=============== SEARCH HIDDEN ===============*/
/* Validate if constant exists */
if(searchClose) {
    searchClose.addEventListener('click', () => {
        searchContent.classList.remove('show-search')
    })
}

/*=============== LOGIN ===============*/
const loginButton = document.getElementById('login-button'),
    loginClose = document.getElementById('login-close'),
    loginContent = document.getElementById('login-content')

/*=============== LOGIN SHOW ===============*/
/* Validate if constant exists */
if(loginButton) {
    loginButton.addEventListener('click', () => {
        loginContent.classList.add('show-login')
    })
}

/*=============== LOGIN HIDDEN ===============*/
/* Validate if constant exists */
if(loginClose) {
    loginClose.addEventListener('click', () => {
        loginContent.classList.remove('show-login')
    })
}

/*=============== ADD SHADOW HEADER ===============*/
const shadowHeader = () => {
    const header = document.getElementById('header')
    this.scrollY >= 50 ? header.classList.add('shadow-header')
                       : header.classList.remove('shadow-header')
}

/*=============== HOME SWIPER ===============*/
let swiperHome = new Swiper('.home__swiper', {
    loop: true,
    spaceBetween: -24,
    grabCursor: true,
    slidesPerView: 'auto',
    centeredSlides: 'auto',

    autoplay: {
        delay: 3000,
        disableOnInteract: false,
    },

    breakpoints: {
        1220: {
            spaceBetween: -32,
        }
    }
});

/*=============== FEATURED SWIPER ===============*/
let swiperFeatured = new Swiper('.featured__swiper', {
    loop: true,
    spaceBetween: 16,
    grabCursor: true,
    slidesPerView: 'auto',
    centeredSlides: 'auto',

    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },

    breakpoints: {
        1150: {
            slidesPerView: 3,
            centeredSlides: true,
        }
    }
});

/*=============== TESTIMONIAL SWIPER ===============*/
let swiperTeam = new Swiper('.team__swiper', {
    loop: true,
    spaceBetween: 16,
    grabCursor: true,
    slidesPerView: 'auto',
    centeredSlides: 'auto',

    breakpoints: {
        1150: {
            slidesPerView: 3,
            centeredSlides: true,
        }
    }
});

/*=============== SHOW SCROLL UP ===============*/ 
const scrollUp = () => {
    const scrollUp = document.getElementById('scroll-up')
    this.scrollY >= 350 ? scrollUp.classList.add('show-scroll')
                        : scrollUp.classList.remove('show-scroll')
}
window.addEventListener('scroll', scrollUp)

/*=============== SCROLL SECTIONS ACTIVE LINK ===============*/
const sections = document.querySelectorAll('section[id]')

const scrollActive = () => {
    const scrollDown = window.scrollY

sections.forEach(current => {
    const sectionHeight = current.offsetHeight,
          sectionTop = current.offsetTop - 58,
          sectionId = current.getAttribute('id'),
          sectionClass = document.querySelector('.nav__nav menu a[href*=' + sectionId + ']')

    if (scrollDown > sectionTop && scrollDown <= sectionTop + sectionHeight) {
        sectionClass.classList.add('active-link')
    } else {
        sectionClass.remove('active-link')
    }
})
}
window.addEventListener('scroll', scrollActive)

/*=============== DARK LIGHT THEME ===============*/ 
const setTheme = (theme) => {
    document.body.classList.toggle('dark-theme', theme === 'dark');
    themeButton.classList.toggle('ri-moon-line', theme === 'light');
    themeButton.classList.toggle('ri-sun-line', theme === 'dark');
    localStorage.setItem('selected-theme', theme);
};

// Function to get the current theme
const getCurrentTheme = () => document.body.classList.contains('dark-theme') ? 'dark' : 'light';

const themeButton = document.getElementById('theme-button');

// Check the theme in localStorage and set it
const savedTheme = localStorage.getItem('selected-theme');
if (savedTheme) {
    setTheme(savedTheme);
} else {
    // Default to light theme
    setTheme('light');
}

themeButton.addEventListener('click', () => {
    const newTheme = getCurrentTheme() === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
});

// User dropdown functionality
const userButton = document.getElementById('user-button');
const userDropdown = document.getElementById('user-dropdown');

if (userButton && userDropdown) {
    userButton.addEventListener('click', (e) => {
        e.preventDefault();
        userDropdown.classList.toggle('show-dropdown');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!userButton.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.classList.remove('show-dropdown');
        }
    });
}


/*=============== SCROLL REVEAL ANIMATION ===============*/
const sr = ScrollReveal({
    origin: 'top',
    distance: '60px',
    duration: 2500,
    delay: 400,
    // reset: true, // Animations repeat
})

sr.reveal('.home__data, .featured__container, .contact__data, .team__container, .footer__container')
sr.reveal('.home__images', {delay: 600})
sr.reveal('.services__card', {interval: 100})
sr.reveal('.download__data', {origin: 'left'})
sr.reveal('.download__images', {origin: 'right'})