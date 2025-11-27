// main.js - Contiene el código JavaScript para la interactividad del portafolio

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar animaciones y eventos
    initSmoothScrolling();
    setupEventListeners();
    initScrollAnimations();
    // Generar estrellas para el fondo
    createStars();
    initThemeToggle(); // Inicializar el alternador de tema

    // Cargar datos dinámicamente para las secciones
    loadSectionData('experience', 'experiencia-list', renderExperience);
    loadSectionData('projects', 'proyectos-list', renderProjects);
    loadSectionData('other_webs', 'otras-webs-list', renderOtherWebs);
    setupImageModal(); // Configurar la funcionalidad del modal de imágenes
});

// Función genérica para cargar datos de una sección
async function loadSectionData(sectionName, elementId, renderFunction) {
    try {
        const response = await fetch(`app/php/get_portfolio_data.php?section=${sectionName}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        const container = document.getElementById(elementId);
        if (container) {
            renderFunction(data, container);
        }
    } catch (error) {
        console.error(`Error al cargar datos de la sección ${sectionName}:`, error);
    }
}

// Funciones de renderizado específicas para cada sección
function renderExperience(experiences, container) {
    container.innerHTML = experiences.map(exp => `
        <div class="timeline-item">
            <h3>${exp.title}</h3>
            <h4>${exp.company}</h4>
            <span class="date">${exp.start_date} - ${exp.end_date}</span>
            <p>${exp.description}</p>
        </div>
    `).join('');
}

function renderProjects(projects, container) {
    container.innerHTML = projects.map(project => `
        <div class="project-item">
            <h3>${project.title}</h3>
            <span class="date">${project.start_date} - ${project.end_date}</span>
            <p>${project.description}</p>
            <a href="${project.url}" target="_blank" class="btn">Ver Proyecto</a>
        </div>
    `).join('');
}

function renderOtherWebs(otherWebs, container) {
    container.innerHTML = otherWebs.map(web => `
        <div class="website-item">
            <h3>${web.title}</h3>
            <p>${web.description}</p>
            <a href="${web.url}" target="_blank" rel="noopener" class="btn">Visitar Web</a>
        </div>
    `).join('');
}

function renderEducation(educationEntries, container) {
    container.innerHTML = educationEntries.map(edu => `
        <div class="timeline-item">
            <h3>${edu.degree}</h3>
            <h4>${edu.institution}</h4>
            <span class="date">${edu.start_date} - ${edu.end_date}</span>
            <span class="location">${edu.location}</span>
        </div>
    `).join('');
}

function initThemeToggle() {
    const themeToggleButton = document.getElementById('theme-toggle-button');
    const body = document.body;

    if (themeToggleButton) { // Añadir comprobación de existencia
        // Cargar el tema guardado del localStorage
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'light-mode') {
            body.classList.add('light-mode');
            const icon = themeToggleButton.querySelector('i');
            if (icon) icon.classList.replace('fa-moon', 'fa-sun');
        } else {
            const icon = themeToggleButton.querySelector('i');
            if (icon) icon.classList.replace('fa-sun', 'fa-moon');
        }

        themeToggleButton.addEventListener('click', () => {
            body.classList.toggle('light-mode');
            const icon = themeToggleButton.querySelector('i');
            if (body.classList.contains('light-mode')) {
                if (icon) icon.classList.replace('fa-moon', 'fa-sun');
                localStorage.setItem('theme', 'light-mode');
            } else {
                if (icon) icon.classList.replace('fa-sun', 'fa-moon');
                localStorage.setItem('theme', 'dark-mode');
            }
        });
    }
}

function initSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();

            const targetElement = document.querySelector(this.getAttribute('href'));
            if (targetElement) { // Añadir comprobación de existencia
                targetElement.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
}

function setupEventListeners() {
    // Configurar evento para el botón de administración
    const adminButton = document.getElementById('admin-button');
    if (adminButton) {
        adminButton.addEventListener('click', () => {
            // Redirigir a la página de verificación de reCAPTCHA
            window.location.href = 'recaptcha_verify.php';
        });
    }

    // Cargar datos del CV dinámicamente (esto se hará más adelante con PHP y la DB)
    // loadCVData();

    // Configurar evento para el botón de desarrollador
    const developerButton = document.getElementById('developer-button');
    const developerPopup = document.getElementById('developer-popup');

    if (developerButton && developerPopup) {
        developerButton.addEventListener('click', () => {
            developerPopup.classList.toggle('show');
        });

        // Cerrar el popup si se hace clic fuera de él
        // Cerrar el popup si se hace clic fuera de él
        document.addEventListener('click', (event) => {
            if (!developerButton.contains(event.target) && !developerPopup.contains(event.target)) {
                developerPopup.classList.remove('show');
            }
        });

        // Cerrar el popup si el mouse sale del contenedor del desarrollador
        const developerInfoContainer = document.querySelector('.developer-info-container');
        if (developerInfoContainer) {
            developerInfoContainer.addEventListener('mouseleave', () => {
                developerPopup.classList.remove('show');
            });
        }
    }

    // Funcionalidad para el menú de usuario en el panel de administración
    const adminUserMenu = document.querySelector('.admin-user-menu');
    const adminUserButton = document.querySelector('.admin-user-btn');
    const adminDropdownMenu = document.querySelector('.admin-dropdown-menu');

    if (adminUserMenu && adminUserButton && adminDropdownMenu) {
        adminUserButton.addEventListener('click', (event) => {
            event.stopPropagation(); // Evitar que el clic se propague y cierre el menú inmediatamente
            adminDropdownMenu.classList.toggle('show-menu');
        });

        adminUserMenu.addEventListener('mouseleave', () => {
            adminDropdownMenu.classList.remove('show-menu');
        });

        // Cerrar el menú si se hace clic fuera de él
        document.addEventListener('click', (event) => {
            if (!adminUserMenu.contains(event.target)) {
                adminDropdownMenu.classList.remove('show-menu');
            }
        });
    }
}

function initScrollAnimations() {
    const sections = document.querySelectorAll('.section');
    const projectItems = document.querySelectorAll('.project-item');
    const certificateItems = document.querySelectorAll('.certificate-item');
    const websiteItems = document.querySelectorAll('.website-item');

    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.2 // Aumentar el umbral para que la animación se active antes
    };

    const sectionObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Todas las secciones usarán la animación 'zoom-in'
                const animationClass = 'zoom-in';
                if (entry.isIntersecting) {
                    entry.target.classList.add(animationClass);
                } else {
                    entry.target.classList.remove(animationClass);
                }
            }
        });
    }, observerOptions);

    const itemObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const animationClass = entry.target.classList.contains('project-item') ? 'fade-in-item' :
                                       entry.target.classList.contains('certificate-item') ? 'slide-in-up-item' :
                                       entry.target.classList.contains('website-item') ? 'fade-in-item' :
                                       'fade-in-item'; // Animación por defecto
              entry.target.classList.add(animationClass);
              entry.target.classList.add('is-visible'); // Añadir la clase is-visible
              observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    if (sections) { // Añadir comprobación de existencia
        sections.forEach(section => {
            sectionObserver.observe(section);
        });
    }

    if (projectItems) { // Añadir comprobación de existencia
        projectItems.forEach(item => {
            itemObserver.observe(item);
        });
    }

    if (certificateItems) { // Añadir comprobación de existencia
        certificateItems.forEach(item => {
            itemObserver.observe(item);
        });
    }

    if (websiteItems) { // Añadir comprobación de existencia
        websiteItems.forEach(item => {
            itemObserver.observe(item);
        });
    }
}


function setupImageModal() {
    const imageModal = document.getElementById('image-modal');
    const modalImage = document.getElementById('modal-image');
    const modalCaption = document.getElementById('modal-caption');
    const closeButton = document.querySelector('.close-button');

    // Configurar certificados
    const certificateItems = document.querySelectorAll('.certificate-item');
    certificateItems.forEach(item => {
        const img = item.querySelector('img');
        const title = item.querySelector('h3');
        if (img && title) {
            img.addEventListener('click', () => {
                modalImage.src = img.src;
                modalImage.alt = title.textContent; // Añadir texto alternativo
                modalCaption.textContent = title.textContent;
                imageModal.style.display = 'block';
            });
        }
    });

    // Configurar foto de perfil
    const profilePic = document.getElementById('profile-pic');
    if (profilePic) {
        profilePic.addEventListener('click', () => {
            modalImage.src = profilePic.src;
            modalImage.alt = profilePic.alt; // Usar el alt existente
            modalCaption.textContent = "Foto de Perfil";
            imageModal.style.display = 'block';
        });
    }

    // Cerrar modal al hacer clic en el botón de cierre
    if (closeButton) {
        closeButton.addEventListener('click', () => {
            imageModal.style.display = 'none';
            modalImage.src = '';
            modalImage.alt = '';
            modalCaption.textContent = '';
        });
    }

    // Cerrar modal al hacer clic fuera de la imagen (en el fondo)
    if (imageModal) {
        imageModal.addEventListener('click', (event) => {
            if (event.target === imageModal) {
                imageModal.style.display = 'none';
                modalImage.src = '';
                modalImage.alt = '';
                modalCaption.textContent = '';
            }
        });
    }
}

function createStars() {
    const starsContainer = document.querySelector('.stars');
    if (starsContainer) { // Añadir comprobación de existencia
        const numStars = 100; // Número de estrellas a generar

        for (let i = 0; i < numStars; i++) {
            const star = document.createElement('div');
            star.classList.add('star');
            star.style.left = `${Math.random() * 100}%`;
            star.style.top = `${Math.random() * 100}%`;
            star.style.width = `${Math.random() * 3 + 1}px`;
            star.style.height = star.style.width;
            star.style.animationDelay = `${Math.random() * 5}s`;
            starsContainer.appendChild(star);
        }
    }
}