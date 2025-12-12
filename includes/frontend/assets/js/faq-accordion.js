/**
 * FAQ Accordion Functionality
 *
 * Handles expand/collapse for FAQ items with smooth animations
 * and accessibility features (ARIA attributes).
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend/assets/js
 * @since      2.35.0
 */

(function() {
	'use strict';

	/**
	 * Initialize FAQ accordion on DOM ready.
	 */
	function initFAQAccordion() {
		const faqSection = document.querySelector('.wta-faq-section');
		
		if (!faqSection) {
			return; // No FAQ section on this page
		}

		const faqItems = faqSection.querySelectorAll('.wta-faq-item');
		
		if (faqItems.length === 0) {
			return;
		}

		// Add click event to each FAQ question button
		faqItems.forEach(function(item) {
			const questionBtn = item.querySelector('.wta-faq-question');
			const answerDiv = item.querySelector('.wta-faq-answer');
			
			if (!questionBtn || !answerDiv) {
				return;
			}

			questionBtn.addEventListener('click', function() {
				toggleFAQItem(item, questionBtn, answerDiv);
			});

			// Keyboard accessibility (Enter and Space)
			questionBtn.addEventListener('keydown', function(e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					toggleFAQItem(item, questionBtn, answerDiv);
				}
			});
		});

		// Check if URL has #faq hash and auto-expand first FAQ
		if (window.location.hash === '#faq' && faqItems.length > 0) {
			const firstItem = faqItems[0];
			const firstBtn = firstItem.querySelector('.wta-faq-question');
			const firstAnswer = firstItem.querySelector('.wta-faq-answer');
			
			if (firstBtn && firstAnswer) {
				expandFAQItem(firstItem, firstBtn, firstAnswer);
				
				// Smooth scroll to FAQ section
				setTimeout(function() {
					faqSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}, 100);
			}
		}
	}

	/**
	 * Toggle FAQ item (expand/collapse).
	 *
	 * @param {HTMLElement} item      FAQ item container.
	 * @param {HTMLElement} button    Question button.
	 * @param {HTMLElement} answerDiv Answer div.
	 */
	function toggleFAQItem(item, button, answerDiv) {
		const isExpanded = button.getAttribute('aria-expanded') === 'true';
		
		if (isExpanded) {
			collapseFAQItem(item, button, answerDiv);
		} else {
			expandFAQItem(item, button, answerDiv);
		}
	}

	/**
	 * Expand FAQ item.
	 *
	 * @param {HTMLElement} item      FAQ item container.
	 * @param {HTMLElement} button    Question button.
	 * @param {HTMLElement} answerDiv Answer div.
	 */
	function expandFAQItem(item, button, answerDiv) {
		// Update ARIA
		button.setAttribute('aria-expanded', 'true');
		answerDiv.removeAttribute('hidden');
		
		// Add expanded class for CSS animation
		item.classList.add('wta-faq-expanded');
		
		// Smooth height animation
		answerDiv.style.maxHeight = answerDiv.scrollHeight + 'px';
	}

	/**
	 * Collapse FAQ item.
	 *
	 * @param {HTMLElement} item      FAQ item container.
	 * @param {HTMLElement} button    Question button.
	 * @param {HTMLElement} answerDiv Answer div.
	 */
	function collapseFAQItem(item, button, answerDiv) {
		// Update ARIA
		button.setAttribute('aria-expanded', 'false');
		
		// Remove expanded class
		item.classList.remove('wta-faq-expanded');
		
		// Collapse animation
		answerDiv.style.maxHeight = '0';
		
		// Hide after animation completes (300ms)
		setTimeout(function() {
			if (button.getAttribute('aria-expanded') === 'false') {
				answerDiv.setAttribute('hidden', '');
			}
		}, 300);
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initFAQAccordion);
	} else {
		// DOM already loaded
		initFAQAccordion();
	}

})();

