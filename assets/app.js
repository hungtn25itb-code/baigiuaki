document.querySelectorAll('.delete-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const instructorName = form.dataset.name || 'giảng viên này';
        if (!window.confirm(`Bạn có chắc muốn xóa ${instructorName}?`)) {
            event.preventDefault();
        }
    });
});

const imageInput = document.querySelector('#image');
const fileHint = document.querySelector('#file-hint');
if (imageInput && fileHint) {
    imageInput.addEventListener('change', () => {
        fileHint.textContent = imageInput.files?.[0]?.name || 'JPG, PNG, WEBP hoặc GIF · tối đa 5 MB';
    });
}

document.querySelectorAll('.notice-close').forEach((button) => {
    button.addEventListener('click', () => button.closest('.notice')?.remove());
});
