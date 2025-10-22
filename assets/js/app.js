const modal = document.getElementById('newsletterModal');
const newsletterForm = document.getElementById('newsletterForm');
const feedback = newsletterForm ? newsletterForm.querySelector('.form-feedback') : null;
const modalCloseButtons = document.querySelectorAll('[data-close-modal]');
const modalStorageKey = 'techmart_newsletter_seen';

function openModal() {
    if (modal) {
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }
}

function closeModal() {
    if (modal) {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }
}

function shouldShowModal() {
    return !localStorage.getItem(modalStorageKey);
}

function markModalSeen() {
    localStorage.setItem(modalStorageKey, '1');
}

if (modal) {
    modal.setAttribute('aria-hidden', modal.hidden ? 'true' : 'false');

    if (shouldShowModal()) {
        window.addEventListener('load', () => {
            setTimeout(openModal, 1200);
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            markModalSeen();
            closeModal();
        }
    });
}

modalCloseButtons.forEach((btn) => btn.addEventListener('click', () => {
    markModalSeen();
    closeModal();
}));

document.addEventListener('click', (event) => {
    if (event.target === modal) {
        markModalSeen();
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
                markModalSeen();
                newsletterForm.reset();
                showFeedback(result.message, false);
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

const mobileToggle = document.querySelector('.mobile-nav-toggle');
const nav = document.querySelector('.primary-nav ul');
if (mobileToggle && nav) {
    mobileToggle.addEventListener('click', () => {
        const expanded = mobileToggle.getAttribute('aria-expanded') === 'true';
        mobileToggle.setAttribute('aria-expanded', String(!expanded));
        nav.classList.toggle('open');
    });
}

const dropdownToggle = document.querySelector('.dropdown-toggle');
const dropdownMenu = document.querySelector('.dropdown-menu');
if (dropdownToggle && dropdownMenu) {
    dropdownToggle.addEventListener('click', () => {
        const expanded = dropdownToggle.getAttribute('aria-expanded') === 'true';
        dropdownToggle.setAttribute('aria-expanded', String(!expanded));
        dropdownMenu.classList.toggle('open');
    });
}
