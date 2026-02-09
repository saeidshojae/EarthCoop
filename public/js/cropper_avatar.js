document.addEventListener('DOMContentLoaded', function () {
    let cropper;
    const avatarInput = document.getElementById('avatar-input');
    const avatarSelectBtn = document.getElementById('avatar-select-btn');
    const cropperModal = new bootstrap.Modal(document.getElementById('cropperModal'));
    const cropperImage = document.getElementById('cropper-image');
    const cropBtn = document.getElementById('crop-btn');
    const avatarPreview = document.getElementById('avatar-preview');
    const avatarPreviewContainer = document.getElementById('avatar-preview-container');
    const croppedAvatarInput = document.getElementById('cropped-avatar');

    function adjustCropperHeight() {
        const container = document.getElementById('cropper-container');
        if (window.innerWidth < 768) {
            container.style.height = '300px';
        } else if (window.innerWidth < 992) {
            container.style.height = '400px';
        } else {
            container.style.height = '500px';
        }
        if (cropper) {
            cropper.refresh?.();
        }
    }

    window.addEventListener('resize', adjustCropperHeight);
    adjustCropperHeight();

    avatarSelectBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        avatarInput.click();
    });

    avatarInput?.addEventListener('change', function (e) {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                cropperImage.src = e.target.result;
                cropperModal.show();

                if (cropper) cropper.destroy();

                adjustCropperHeight();

                cropper = new Cropper(cropperImage, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.9,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                    minContainerWidth: window.innerWidth < 768 ? 300 : 500,
                    minContainerHeight: window.innerWidth < 768 ? 300 : 500,
                    responsive: true,
                    touchDragZoom: true,
                });
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    cropBtn?.addEventListener('click', function () {
        if (cropper) {
            const canvas = cropper.getCroppedCanvas({ width: 400, height: 400 });
            canvas.toBlob(function (blob) {
                const croppedFile = new File([blob], 'cropped-avatar.jpg', { type: 'image/jpeg' });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(croppedFile);
                avatarInput.files = dataTransfer.files;

                avatarPreview.src = URL.createObjectURL(blob);
                avatarPreviewContainer.style.display = 'block';

                croppedAvatarInput.value = canvas.toDataURL('image/jpeg');
                cropperModal.hide();
            }, 'image/jpeg', 0.9);
        }
    });
});