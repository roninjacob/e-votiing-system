/**
 * app.js — Block Election Module
 *
 * Handles:
 * 1. Candidate card selection with max_votes enforcement
 * 2. Ballot confirm modal — builds a summary before final submit
 */

document.addEventListener('DOMContentLoaded', () => {

    // ─── 1. Candidate Card Selection ─────────────────────────────────────────

    document.querySelectorAll('.candidate-group').forEach(group => {
        const max      = parseInt(group.dataset.max, 10) || 1;
        const cards    = group.querySelectorAll('.candidate-card');
        const position = group.dataset.position;

        cards.forEach(card => {
            const checkbox = card.querySelector('.candidate-checkbox');
            card.classList.toggle('selected', checkbox.checked);
            checkbox.addEventListener('change', () => {
                const isSelected = !checkbox.checked;

                if (!isSelected) {
                    // Count currently selected in this group
                    const selectedCount = group.querySelectorAll('.candidate-card.selected').length;
                    if (selectedCount >= max) {
                        checkbox.checked = false;
                        // Enforce max — flash a brief visual cue instead of alert
                        group.querySelectorAll('.candidate-card:not(.selected)').forEach(c => {
                            c.classList.add('disabled');
                            setTimeout(() => c.classList.remove('disabled'), 600);
                        });
                        return;
                    }
                    card.classList.add('selected');
                    checkbox.checked = true;
                } else {
                    card.classList.remove('selected');
                    checkbox.checked = false;
                }

                // Re-evaluate disabled state after each toggle
                const nowSelected = group.querySelectorAll('.candidate-card.selected').length;
                cards.forEach(c => {
                    if (!c.classList.contains('selected')) {
                        c.classList.toggle('disabled', nowSelected >= max);
                    }
                });
            });
        });
    });

    // ─── 2. Confirm Modal ─────────────────────────────────────────────────────

    const btnConfirm = document.getElementById('btnConfirm');
    const btnSubmit  = document.getElementById('btnSubmit');
    const ballotForm = document.getElementById('ballotForm');
    const summary    = document.getElementById('confirmSummary');

    if (!btnConfirm || !btnSubmit || !ballotForm || !summary) return;

    // Keep the dialog above Bootstrap's backdrop, outside the app stacking context.
    const modalElement = document.getElementById('confirmModal');
    document.body.appendChild(modalElement);
    const confirmModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    let canSubmit = false;

    btnConfirm.addEventListener('click', () => {
        // Validate required positions before opening modal
        let valid = true;
        const lines = [];

        document.querySelectorAll('.candidate-group').forEach(group => {
            const posName  = group.dataset.position;
            const required = group.dataset.required === '1';
            const selected = [...group.querySelectorAll('.candidate-checkbox:checked')]
                .map(checkbox => checkbox.closest('.candidate-card'));

            if (required && selected.length === 0) {
                valid = false;
                lines.push(`<li class="text-danger">${escHtml(posName)}: <em>required — please select a candidate</em></li>`);
            } else if (selected.length === 0) {
                lines.push(`<li class="text-muted">${escHtml(posName)}: <em>abstained</em></li>`);
            } else {
                const names = [...selected].map(c => escHtml(c.querySelector('.fw-semibold')?.textContent?.trim() ?? ''));
                lines.push(`<li><strong>${escHtml(posName)}:</strong> ${names.join(', ')}</li>`);
            }
        });

        canSubmit = valid;
        btnSubmit.disabled = !valid;
        if (!valid) {
            summary.innerHTML = '<ul class="mb-0">' + lines.join('') + '</ul>'
                + '<p class="text-danger mt-2 mb-0">Please fix the errors above before submitting.</p>';
            confirmModal.show();
            return;
        }

        summary.innerHTML = '<p>Please review your selections:</p><ul class="mb-0">' + lines.join('') + '</ul>';
        confirmModal.show();
    });

    btnSubmit.addEventListener('click', () => {
        if (!canSubmit) return;
        // Disable button to prevent double-submit
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting…';
        ballotForm.submit();
    });

    // ─── Utility ──────────────────────────────────────────────────────────────

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
});
