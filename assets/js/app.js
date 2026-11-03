document.addEventListener('DOMContentLoaded', () => {
    // Confirm potentially irreversible actions such as patient discharge.
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirm || '¿Deseas continuar?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    // Only display medical visits belonging to the selected active admission.
    const admissionSelect = document.getElementById('diagnosis-admission');
    const visitSelect = document.getElementById('diagnosis-visit');
    if (admissionSelect && visitSelect) {
        const filterVisits = () => {
            const selectedAdmission = admissionSelect.value;
            Array.from(visitSelect.options).forEach((option, index) => {
                if (index === 0) return;
                const belongs = option.dataset.admission === selectedAdmission;
                option.hidden = selectedAdmission !== '' && !belongs;
                if (!belongs && option.selected) visitSelect.value = '';
            });
        };
        admissionSelect.addEventListener('change', filterVisits);
        filterVisits();
    }
});
