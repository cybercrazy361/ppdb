// admin_receipt_layout.js

document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('layoutGrid');
    const paperWidthInput = document.getElementById('paper_width_mm');
    const paperHeightInput = document.getElementById('paper_height_mm');
    let currentElement = null;
    let offsetX = 0;
    let offsetY = 0;

    // Fungsi untuk memulai drag
    function startDrag(e) {
        e.preventDefault();
        currentElement = e.currentTarget;
        currentElement.classList.add('dragging');

        const rect = currentElement.getBoundingClientRect();
        offsetX = e.clientX - rect.left;
        offsetY = e.clientY - rect.top;

        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', stopDrag);
    }

    // Fungsi untuk drag
    function drag(e) {
        if (!currentElement) return;

        const gridRect = grid.getBoundingClientRect();
        let x = e.clientX - gridRect.left - offsetX;
        let y = e.clientY - gridRect.top - offsetY;

        // Batasi posisi agar tidak keluar dari grid
        x = Math.max(0, Math.min(x, grid.offsetWidth - currentElement.offsetWidth));
        y = Math.max(0, Math.min(y, grid.offsetHeight - currentElement.offsetHeight));

        // Konversi posisi ke mm (asumsi 1px = 0.264583 mm)
        const x_mm = (x / grid.offsetWidth) * parseFloat(paperWidthInput.value);
        const y_mm = (y / grid.offsetHeight) * parseFloat(paperHeightInput.value);

        currentElement.style.left = `${x_mm.toFixed(1)}mm`;
        currentElement.style.top = `${y_mm.toFixed(1)}mm`;

        // Update nilai input form
        const elementName = currentElement.getAttribute('data-element');
        const xInput = document.querySelector(`input[name="x_${elementName}"]`);
        const yInput = document.querySelector(`input[name="y_${elementName}"]`);

        if (xInput && yInput) {
            xInput.value = x_mm.toFixed(1);
            yInput.value = y_mm.toFixed(1);
        }
    }

    // Fungsi untuk menghentikan drag
    function stopDrag() {
        if (currentElement) {
            currentElement.classList.remove('dragging');
            currentElement = null;
        }
        document.removeEventListener('mousemove', drag);
        document.removeEventListener('mouseup', stopDrag);
    }

    // Menambahkan event listener untuk semua elemen layout
    const elements = document.querySelectorAll('.layout-element');
    elements.forEach(element => {
        element.addEventListener('mousedown', startDrag);
    });

    // Menyesuaikan ukuran grid secara dinamis berdasarkan input ukuran kertas
    paperWidthInput.addEventListener('input', updateGridSize);
    paperHeightInput.addEventListener('input', updateGridSize);

    function updateGridSize() {
        const width = parseFloat(paperWidthInput.value);
        const height = parseFloat(paperHeightInput.value);
        grid.style.width = `${width}mm`;
        grid.style.height = `${height}mm`;

        // Mengupdate posisi elemen sesuai dengan ukuran baru
        const elements = document.querySelectorAll('.layout-element');
        elements.forEach(element => {
            const elemName = element.getAttribute('data-element');
            const xInput = document.querySelector(`input[name="x_${elemName}"]`);
            const yInput = document.querySelector(`input[name="y_${elemName}"]`);
            const fontSizeInput = document.querySelector(`input[name="font_size_${elemName}"]`);
            const fontFamilyInput = document.querySelector(`input[name="font_family_${elemName}"]`);

            const newX = parseFloat(xInput.value);
            const newY = parseFloat(yInput.value);
            const newFontSize = fontSizeInput.value;
            const newFontFamily = fontFamilyInput.value;

            if (elemName === 'logo' && element.querySelector('img')) {
                // Logo sebagai gambar
                element.style.left = `${newX}mm`;
                element.style.top = `${newY}mm`;
            } else if (elemName === 'watermark') {
                // Watermark sebagai teks
                element.style.left = `${newX}mm`;
                element.style.top = `${newY}mm`;
                element.style.fontSize = `${newFontSize}pt`;
                element.style.fontFamily = `${newFontFamily}`;
            } else {
                // Elemen lainnya
                element.style.left = `${newX}mm`;
                element.style.top = `${newY}mm`;
                element.style.fontSize = `${newFontSize}pt`;
                element.style.fontFamily = `${newFontFamily}`;
            }
        });
    }
});
