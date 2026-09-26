document.addEventListener('DOMContentLoaded', function() {
    // One-click Ticket ID copy
    const copyBtns = document.querySelectorAll('.nik-vd-copy-btn');
    copyBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const textToCopy = this.getAttribute('data-copy');
            if (!textToCopy) return;

            navigator.clipboard.writeText(textToCopy).then(() => {
                const origHtml = this.innerHTML;
                this.innerHTML = '<span class="dashicons dashicons-yes" style="color: #22c55e;"></span>';
                setTimeout(() => {
                    this.innerHTML = origHtml;
                }, 1500);
            }).catch(err => {
                console.error('Failed to copy text: ', err);
            });
        });
    });
});
