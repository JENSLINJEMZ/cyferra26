

// Loading Screen
window.addEventListener('load', () => {
    setTimeout(() => {
        document.querySelector('.loading-screen').style.opacity = '0';
        setTimeout(() => {
            document.querySelector('.loading-screen').style.display = 'none';
        }, 800);
    }, 3000);
});

// Particle.js Configuration
particlesJS('particles-js', {
    particles: {
        number: {
            value: 100,
            density: {
                enable: true,
                value_area: 800
            }
        },
        color: {
            value: ['#00d4ff', '#b300ff', '#ff00cc']
        },
        shape: {
            type: 'circle'
        },
        opacity: {
            value: 0.6,
            random: true,
            anim: {
                enable: true,
                speed: 1,
                opacity_min: 0.1,
                sync: false
            }
        },
        size: {
            value: 4,
            random: true,
            anim: {
                enable: true,
                speed: 2,
                size_min: 0.1,
                sync: false
            }
        },
        line_linked: {
            enable: true,
            distance: 150,
            color: '#00d4ff',
            opacity: 0.4,
            width: 1
        },
        move: {
            enable: true,
            speed: 3,
            direction: 'none',
            random: false,
            straight: false,
            out_mode: 'out',
            bounce: false,
            attract: {
                enable: false,
                rotateX: 600,
                rotateY: 1200
            }
        }
    },
    interactivity: {
        detect_on: 'canvas',
        events: {
            onhover: {
                enable: true,
                mode: 'repulse'
            },
            onclick: {
                enable: true,
                mode: 'push'
            },
            resize: true
        },
        modes: {
            grab: {
                distance: 400,
                line_linked: {
                    opacity: 1
                }
            },
            bubble: {
                distance: 400,
                size: 40,
                duration: 2,
                opacity: 8,
                speed: 3
            },
            repulse: {
                distance: 200,
                duration: 0.4
            },
            push: {
                particles_nb: 4
            },
            remove: {
                particles_nb: 2
            }
        }
    },
    retina_detect: true
});

// Sticky Header
let lastScroll = 0;
window.addEventListener('scroll', () => {
    const header = document.querySelector('.sticky-header');
    const currentScroll = window.pageYOffset;
    
    if (currentScroll <= 0) {
        header.style.background = 'rgba(5, 5, 16, 0.95)';
        header.style.boxShadow = 'none';
    } else {
        header.style.background = 'rgba(5, 5, 16, 0.98)';
        header.style.boxShadow = '0 5px 30px rgba(0, 0, 0, 0.3)';
    }
    
    lastScroll = currentScroll;
});

// Smooth Scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
            
            // Close mobile menu if open
            if (navMenu.classList.contains('active')) {
                navMenu.classList.remove('active');
                hamburger.classList.remove('active');
            }
        }
    });
});

// Mobile Menu Toggle
const hamburger = document.querySelector('.hamburger');
const navMenu = document.querySelector('.nav-menu');

hamburger.addEventListener('click', () => {
    navMenu.classList.toggle('active');
    hamburger.classList.toggle('active');
});

// Countdown Timer
function updateCountdown() {
    const eventDate = new Date('2026-02-27T09:00:00').getTime();
    const now = new Date().getTime();
    const distance = eventDate - now;
    
    if (distance < 0) {
        document.getElementById('days').textContent = '00';
        document.getElementById('hours').textContent = '00';
        document.getElementById('minutes').textContent = '00';
        document.getElementById('seconds').textContent = '00';
        return;
    }
    
    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    
    document.getElementById('days').textContent = String(days).padStart(2, '0');
    document.getElementById('hours').textContent = String(hours).padStart(2, '0');
    document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
    document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
}

setInterval(updateCountdown, 1000);
updateCountdown();

// Event Filtering
const tabBtns = document.querySelectorAll('.tab-btn');
const eventCards = document.querySelectorAll('.event-card');

tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        // Remove active class from all buttons
        tabBtns.forEach(b => b.classList.remove('active'));
        
        // Add active class to clicked button
        btn.classList.add('active');
        
        const filter = btn.getAttribute('data-tab');
        
        // Show/hide event cards based on filter
        eventCards.forEach(card => {
            if (filter === 'all' || card.getAttribute('data-category') === filter) {
                card.style.display = 'flex';
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 10);
            } else {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.display = 'none';
                }, 300);
            }
        });
    });
});

// Event Card Click Effect
document.querySelectorAll('.event-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const card = this.closest('.event-card');
        const eventName = card.querySelector('h3').textContent;
        
        // Create modal for event details
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(5, 5, 16, 0.95);
            color: white;
            padding: 40px;
            border-radius: 20px;
            z-index: 10000;
            animation: fadeInUp 0.5s ease;
            border: 2px solid var(--primary-color);
            backdrop-filter: blur(10px);
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        `;
        
        modal.innerHTML = `
            <h3 style="color: var(--primary-color); margin-bottom: 20px; font-size: 1.8rem;">${eventName}</h3>
            <div style="margin-bottom: 20px;">
                <p><strong>Category:</strong> ${card.querySelector('.event-category').textContent}</p>
                <p><strong>Duration:</strong> ${card.querySelector('.event-details li:nth-child(1)').textContent.replace('<i class="fas fa-clock"></i> ', '')}</p>
                <p><strong>Team Size:</strong> ${card.querySelector('.event-details li:nth-child(2)').textContent.replace('<i class="fas fa-user"></i> ', '')}</p>
                <p><strong>Prize:</strong> ${card.querySelector('.event-details li:nth-child(3)').textContent.replace('<i class="fas fa-trophy"></i> ', '')}</p>
            </div>
            <div style="margin-bottom: 30px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">Description</h4>
                <p>${card.querySelector('p').textContent}</p>
            </div>
            <div style="margin-bottom: 30px;">
                <h4 style="color: var(--primary-color); margin-bottom: 10px;">Rules & Guidelines</h4>
                <ul style="padding-left: 20px; color: var(--text-secondary);">
                    <li>Participants must bring their college ID card</li>
                    <li>All decisions by the judges will be final</li>
                    <li>Plagiarism will lead to disqualification</li>
                    <li>All participants must adhere to the code of conduct</li>
                </ul>
            </div>
            <button id="closeModal" style="
                padding: 12px 30px;
                background: var(--primary-color);
                color: white;
                border: none;
                border-radius: 25px;
                cursor: pointer;
                font-weight: 600;
                display: block;
                margin: 0 auto;
            ">Close</button>
        `;
        
        // Add overlay
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        `;
        
        document.body.appendChild(overlay);
        document.body.appendChild(modal);
        
        // Close modal
        document.getElementById('closeModal').addEventListener('click', () => {
            overlay.remove();
            modal.remove();
        });
        
        overlay.addEventListener('click', () => {
            overlay.remove();
            modal.remove();
        });
    });
});

// Add animation to elements on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

// Observe elements for animation
document.querySelectorAll('.event-card, .stat-item, .timeline-content, .sponsor-item, .contact-item').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
});

// Add ripple effect to buttons
document.querySelectorAll('.glow-btn, .outline-btn, .event-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const x = e.clientX - e.target.getBoundingClientRect().left;
        const y = e.clientY - e.target.getBoundingClientRect().top;
        
        const ripple = document.createElement('span');
        ripple.style.cssText = `
            position: absolute;
            background: rgba(255, 255, 255, 0.4);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(0);
            animation: rippleEffect 0.6s ease-out;
            pointer-events: none;
            left: ${x}px;
            top: ${y}px;
        `;
        
        this.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
    });
});

// Add CSS for ripple animation
const style = document.createElement('style');
style.textContent = `
    @keyframes rippleEffect {
        to {
            transform: translate(-50%, -50%) scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
