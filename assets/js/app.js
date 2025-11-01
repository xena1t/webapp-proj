const modal = document.getElementById('newsletterModal');
const newsletterForm = document.getElementById('newsletterForm');
const feedback = newsletterForm ? newsletterForm.querySelector('.form-feedback') : null;
const modalCloseButtons = document.querySelectorAll('[data-close-modal]');
const openModalTriggers = document.querySelectorAll('[data-open-modal]');
const newsletterStorageKey = 'techmart_newsletter_dismissed';
let modalHasOpened = false;
let autoOpenScheduled = false;

function hasDismissedNewsletter() {
    try {
        return window.localStorage && localStorage.getItem(newsletterStorageKey) === '1';
    } catch (error) {
        return false;
    }
}

function persistNewsletterDismissal() {
    try {
        if (window.localStorage) {
            localStorage.setItem(newsletterStorageKey, '1');
        }
    } catch (error) {
        // Ignore storage failures (e.g., private mode)
    }
}

function openModal(options = {}) {
    if (!modal) return;

    const auto = options.auto ?? false;
    if (auto && (modalHasOpened || hasDismissedNewsletter())) {
        return;
    }

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    modalHasOpened = true;
}

function closeModal() {
    if (!modal) return;

    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    persistNewsletterDismissal();
}

if (modal) {
    modal.setAttribute('aria-hidden', modal.hidden ? 'true' : 'false');

    const scheduleAutoOpen = () => {
        if (autoOpenScheduled) {
            return;
        }

        autoOpenScheduled = true;

        const schedule = window.requestAnimationFrame || ((callback) => setTimeout(callback, 0));

        schedule(() => {
            openModal({ auto: true });
        });
    };

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        scheduleAutoOpen();
    } else {
        document.addEventListener('DOMContentLoaded', scheduleAutoOpen, { once: true });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
}

modalCloseButtons.forEach((btn) =>
    btn.addEventListener('click', () => {
        closeModal();
    })
);

openModalTriggers.forEach((btn) =>
    btn.addEventListener('click', () => {
        openModal({ auto: false });
    })
);

document.addEventListener('click', (event) => {
    if (event.target === modal) {
        closeModal();
    }
});

if (newsletterForm) {
    newsletterForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        feedback.hidden = true;
        feedback.textContent = '';

        const formData = new FormData(newsletterForm);
        const email = formData.get('email');
        const preference = formData.get('preference');
        const budget = formData.get('budget');
        const terms = formData.get('terms');

        const validationErrors = [];

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            validationErrors.push('Please enter a valid email address.');
        }

        if (!preference) {
            validationErrors.push('Please select your primary interest.');
        }

        if (!budget) {
            validationErrors.push('Please choose a budget focus.');
        }

        if (!terms) {
            validationErrors.push('Please accept the terms to continue.');
        }

        if (validationErrors.length > 0) {
            showFeedback(validationErrors, true);
            return;
        }

        try {
            const response = await fetch(newsletterForm.action, {
                method: 'POST',
                body: formData,
            });

            const result = await response.json();

            if (result.success) {
                newsletterForm.reset();
                showFeedback(result.message, false);
                persistNewsletterDismissal();
                setTimeout(closeModal, 1800);
            } else {
                showFeedback(result.message || 'Something went wrong. Please try again.', true);
            }
        } catch (error) {
            showFeedback('Unable to submit right now. Please try again later.', true);
        }
    });
}

function showFeedback(message, isError) {
    if (!feedback) return;
    const text = Array.isArray(message) ? message.join('\n') : message;
    feedback.textContent = text;
    feedback.hidden = false;
    feedback.classList.toggle('error', Boolean(isError));
}

const sidebar = document.querySelector('.sidebar');
const sidebarToggle = document.querySelector('.sidebar-toggle');

function closeSidebar() {
    if (!sidebar) return;

    sidebar.classList.remove('open');
    document.body.classList.remove('nav-open');
    if (sidebarToggle) {
        sidebarToggle.setAttribute('aria-expanded', 'false');
    }
}

if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => {
        const expanded = sidebarToggle.getAttribute('aria-expanded') === 'true';
        const nextExpanded = !expanded;
        sidebarToggle.setAttribute('aria-expanded', String(nextExpanded));
        sidebar.classList.toggle('open', nextExpanded);
        document.body.classList.toggle('nav-open', nextExpanded);
    });
}

document.addEventListener('click', (event) => {
    if (!sidebar || !sidebarToggle) return;
    if (!sidebar.classList.contains('open')) return;

    if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
        closeSidebar();
    }
});

window.addEventListener('resize', () => {
    if (window.innerWidth >= 960) {
        closeSidebar();
    }
});

function initCarousels() {
    const schedule = typeof window.requestAnimationFrame === 'function'
        ? window.requestAnimationFrame.bind(window)
        : (callback) => window.setTimeout(callback, 16);

    document.querySelectorAll('[data-carousel]').forEach((carousel) => {
        const track = carousel.querySelector('[data-carousel-track]');
        const cards = track ? Array.from(track.querySelectorAll('[data-product-card]')) : [];
        if (!track || cards.length === 0) {
            return;
        }

        const container = carousel.closest('[data-carousel-container]')
            || carousel.closest('section')
            || carousel.parentElement;
        const prev = container ? container.querySelector('[data-carousel-prev]') : null;
        const next = container ? container.querySelector('[data-carousel-next]') : null;

        const clampIndex = (value) => Math.max(0, Math.min(value, cards.length - 1));

        const getStep = () => {
            const firstCard = cards[0];
            const style = window.getComputedStyle(track);
            const gapValue = Number.parseFloat(style.columnGap || style.gap || '0');
            const gap = Number.isFinite(gapValue) ? gapValue : 0;
            const width = firstCard
                ? firstCard.getBoundingClientRect().width
                : carousel.clientWidth;
            return width + gap;
        };

        let currentIndex = 0;
        let ticking = false;

        const updateState = () => {
            const step = getStep();
            const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth - 1);
            const atStart = track.scrollLeft <= 0;
            const atEnd = track.scrollLeft >= maxScroll;

            carousel.classList.toggle('at-start', atStart);
            carousel.classList.toggle('at-end', atEnd);

            if (prev) {
                prev.disabled = atStart;
            }

            if (next) {
                next.disabled = atEnd;
            }

            if (step > 0) {
                currentIndex = clampIndex(Math.round(track.scrollLeft / step));
            } else {
                currentIndex = 0;
            }
        };

        const scrollToIndex = (index) => {
            const targetIndex = clampIndex(index);
            const step = getStep();
            currentIndex = targetIndex;
            track.scrollTo({
                left: targetIndex * step,
                behavior: 'smooth',
            });
            schedule(updateState);
        };

        const handleScroll = () => {
            if (ticking) {
                return;
            }

            ticking = true;
            schedule(() => {
                ticking = false;
                updateState();
            });
        };

        if (prev) {
            prev.addEventListener('click', () => {
                scrollToIndex(currentIndex - 1);
            });
        }

        if (next) {
            next.addEventListener('click', () => {
                scrollToIndex(currentIndex + 1);
            });
        }

        track.addEventListener('scroll', handleScroll, { passive: true });
        track.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowRight') {
                event.preventDefault();
                scrollToIndex(currentIndex + 1);
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                scrollToIndex(currentIndex - 1);
            }
        });

        window.addEventListener('resize', () => {
            schedule(updateState);
        });

        updateState();
    });
}

async function toggleWishlist(button) {
    if (!button || button.disabled) {
        return;
    }

    const productId = Number.parseInt(button.dataset.productId || '', 10);
    if (!Number.isInteger(productId) || productId <= 0) {
        return;
    }

    const isActive = button.getAttribute('aria-pressed') === 'true';
    button.disabled = true;

    try {
        const response = await fetch('scripts/wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                action: isActive ? 'remove' : 'add',
            }),
        });

        const result = await response.json().catch(() => ({ success: false }));
        if (!response.ok || !result.success) {
            const message = result && result.message ? result.message : 'Unable to update wishlist. Please try again.';
            alert(message);
            return;
        }

        const inWishlist = Boolean(result.inWishlist);
        button.setAttribute('aria-pressed', inWishlist ? 'true' : 'false');
        button.classList.toggle('active', inWishlist);
    } catch (error) {
        alert('Unable to update wishlist. Please try again later.');
    } finally {
        button.disabled = false;
    }
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-wishlist-toggle]');
    if (toggle) {
        event.preventDefault();
        toggleWishlist(toggle);
    }
});

initCarousels();

document.querySelectorAll('.sidebar a').forEach((link) => {
    link.addEventListener('click', () => {
        closeSidebar();
    });
});

document.querySelectorAll('[data-tab-container]').forEach((container) => {
    const buttons = Array.from(container.querySelectorAll('[data-tab]'));
    const panels = Array.from(container.querySelectorAll('[data-tab-panel]'));

    if (!buttons.length || !panels.length) {
        return;
    }

    const activateTab = (targetId) => {
        buttons.forEach((button) => {
            const isActive = button.getAttribute('data-tab') === targetId;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            const isActive = panel.getAttribute('data-tab-panel') === targetId;
            panel.classList.toggle('active', isActive);
            panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });
    };

    const initial = buttons.find((button) => button.classList.contains('active'))
        || buttons[0];
    activateTab(initial.getAttribute('data-tab'));

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            activateTab(button.getAttribute('data-tab'));
        });
    });
});

document.querySelectorAll('.sidebar-dropdown').forEach((dropdown) => {
    const trigger = dropdown.querySelector('[data-toggle-submenu]')
        || dropdown.querySelector('.sidebar-label, .sidebar-link');
    const submenu = dropdown.querySelector('.sidebar-submenu');

    if (!trigger || !submenu) return;

    const openDropdown = () => {
        dropdown.classList.add('open');
        trigger.setAttribute('aria-expanded', 'true');
        submenu.hidden = false;
        submenu.setAttribute('aria-hidden', 'false');
    };

    const closeDropdown = () => {
        dropdown.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
        submenu.hidden = true;
        submenu.setAttribute('aria-hidden', 'true');
    };

    const toggleDropdown = (event) => {
        event.preventDefault();
        const expanded = trigger.getAttribute('aria-expanded') === 'true';
        if (expanded) {
            closeDropdown();
        } else {
            openDropdown();
        }
    };

    if (trigger.classList.contains('active')) {
        openDropdown();
    } else {
        closeDropdown();
    }

    trigger.addEventListener('click', toggleDropdown);
    dropdown.addEventListener('mouseenter', openDropdown);
    dropdown.addEventListener('mouseleave', closeDropdown);
});
