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

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showFeedback('Please enter a valid email address.', true);
            return;
        }

        if (!preference) {
            showFeedback('Please select your primary interest.', true);
            return;
        }

        if (!budget) {
            showFeedback('Please choose a budget focus.', true);
            return;
        }

        if (!terms) {
            showFeedback('Please accept the terms to continue.', true);
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
    feedback.textContent = message;
    feedback.hidden = false;
    feedback.classList.toggle('error', isError);
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
    const trigger = dropdown.querySelector('.sidebar-label, .sidebar-link');
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

    closeDropdown();

    dropdown.addEventListener('mouseenter', openDropdown);
    dropdown.addEventListener('mouseleave', closeDropdown);
});