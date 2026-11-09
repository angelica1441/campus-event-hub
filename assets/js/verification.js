/* =====================================================
   CAMPUS EVENT HUB
   STUDENT IDENTITY VERIFICATION
   Camera + Image Preview + Validation
===================================================== */

document.addEventListener("DOMContentLoaded", () => {

    // EXISTING PHP HTML ELEMENTS
    const schoolIdInput = document.getElementById("school_id");
    const schoolIdPreview = document.getElementById("idPreview");

    const cameraVideo = document.getElementById("camera");
    const cameraCanvas = document.getElementById("canvas");

    const selfiePreview = document.getElementById("selfiePreview");
    const selfieData = document.getElementById("selfie");

    const startCameraBtn = document.getElementById("startCamera");
    const captureBtn = document.getElementById("captureSelfie");
    const retakeBtn = document.getElementById("retakeSelfie");

    const verificationForm = document.getElementById("verificationForm");

    let cameraStream = null;
    let selfieCaptured = false;

    const MAX_FILE_SIZE = 5 * 1024 * 1024;

    const allowedTypes = [
        "image/jpeg",
        "image/png",
        "image/webp"
    ];

    // ==============================================
    // SCHOOL ID IMAGE PREVIEW
    // ==============================================

    schoolIdInput?.addEventListener("change", function () {

        const file = this.files[0];

        if (!file) {
            schoolIdPreview.hidden = true;
            schoolIdPreview.removeAttribute("src");
            return;
        }

        if (!allowedTypes.includes(file.type)) {
            alert("Invalid file type. Please upload JPG, PNG, or WEBP.");

            this.value = "";
            schoolIdPreview.hidden = true;
            return;
        }

        if (file.size > MAX_FILE_SIZE) {
            alert("School ID image must not exceed 5MB.");

            this.value = "";
            schoolIdPreview.hidden = true;
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            schoolIdPreview.src = event.target.result;
            schoolIdPreview.hidden = false;
        };

        reader.readAsDataURL(file);

    });

    // ==============================================
    // START LIVE CAMERA
    // ==============================================

    async function startCamera() {

        if (!navigator.mediaDevices?.getUserMedia) {
            alert("Camera is unavailable. Please use localhost or HTTPS.");
            return;
        }

        try {

            stopCamera();

            cameraStream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: "user",
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            });

            cameraVideo.srcObject = cameraStream;

            await cameraVideo.play();

            cameraVideo.style.display = "block";

            captureBtn.disabled = false;
            captureBtn.style.display = "inline-flex";

            startCameraBtn.style.display = "none";

        } catch (error) {

            console.error("Camera error:", error);

            stopCamera();

            alert(
                "Unable to access your camera. Please allow camera permission and try again."
            );

        }

    }

    // ==============================================
    // CAPTURE LIVE SELFIE
    // ==============================================

    function captureSelfie() {

        if (!cameraStream || !cameraVideo.videoWidth) {
            alert("Please wait until your camera is ready.");
            return;
        }

        const context = cameraCanvas.getContext("2d");

        cameraCanvas.width = cameraVideo.videoWidth;
        cameraCanvas.height = cameraVideo.videoHeight;

        // Mirror selfie to match the front camera preview.
        context.translate(cameraCanvas.width, 0);
        context.scale(-1, 1);

        context.drawImage(
            cameraVideo,
            0,
            0,
            cameraCanvas.width,
            cameraCanvas.height
        );

        context.setTransform(1, 0, 0, 1, 0, 0);

        const imageData = cameraCanvas.toDataURL("image/jpeg", 0.85);

        selfieData.value = imageData;

        selfiePreview.src = imageData;
        selfiePreview.hidden = false;

        selfieCaptured = true;

        stopCamera();

        captureBtn.style.display = "none";
        retakeBtn.hidden = false;

    }

    // ==============================================
    // STOP CAMERA
    // ==============================================

    function stopCamera() {

        if (cameraStream) {

            cameraStream.getTracks().forEach(track => track.stop());

            cameraStream = null;

        }

        if (cameraVideo) {
            cameraVideo.pause();
            cameraVideo.srcObject = null;
            cameraVideo.style.display = "none";
        }

        if (captureBtn) {
            captureBtn.disabled = true;
        }

        if (startCameraBtn) {
            startCameraBtn.style.display = "inline-flex";
        }

    }

    // ==============================================
    // RETAKE SELFIE
    // ==============================================

    retakeBtn?.addEventListener("click", async () => {

        selfieData.value = "";
        selfieCaptured = false;

        selfiePreview.src = "";
        selfiePreview.hidden = true;

        retakeBtn.hidden = true;
        captureBtn.style.display = "inline-flex";

        await startCamera();

    });

    // ==============================================
    // BUTTON EVENTS
    // ==============================================

    startCameraBtn?.addEventListener("click", startCamera);

    captureBtn?.addEventListener("click", captureSelfie);

    // ==============================================
    // FORM VALIDATION
    // ==============================================

    verificationForm?.addEventListener("submit", function (event) {

        if (!schoolIdInput.files.length) {

            event.preventDefault();

            alert("Please upload your School ID.");

            schoolIdInput.focus();

            return;

        }

        if (!selfieCaptured || !selfieData.value) {

            event.preventDefault();

            alert("Please capture your live selfie using your camera.");

            return;

        }

    });

    // Stop camera when leaving the page.
    window.addEventListener("pagehide", stopCamera);

});