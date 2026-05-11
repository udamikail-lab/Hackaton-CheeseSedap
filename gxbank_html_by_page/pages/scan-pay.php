<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /gxbank_html_by_page/index.php");
    exit();
}
?>

<!doctype html>
<html lang="en" class="h-full">
<head>
<title>Scan & Pay</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
* { font-family: 'DM Sans', sans-serif; }
body { box-sizing: border-box; }
#reader video { border-radius: 18px; }
</style>
</head>

<body class="h-full">
<div class="h-full w-full bg-slate-950 overflow-auto">
    <div class="max-w-md mx-auto px-5 py-8">

        <a href="/gxbank_html_by_page/pages/gx-account.php" class="flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-6 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            <span class="text-sm font-medium">Back</span>
        </a>

        <h1 class="text-2xl font-bold text-white mb-2">Scan & Pay</h1>
        <p class="text-slate-400 text-sm mb-6">
            Scan GX QR code or upload QR screenshot to pay.
        </p>

        <div id="errorBox" class="hidden bg-red-500/15 text-red-300 rounded-xl p-3 text-sm mb-4"></div>
        <div id="successBox" class="hidden bg-emerald-500/15 text-emerald-300 rounded-xl p-3 text-sm mb-4"></div>

        <div class="bg-slate-900 rounded-2xl p-4 border border-slate-800 mb-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center">
                    <i data-lucide="camera" class="w-5 h-5 text-emerald-400"></i>
                </div>

                <div>
                    <p class="text-white font-semibold">Camera Scan</p>
                    <p class="text-slate-400 text-xs">Use camera to scan GX QR code</p>
                </div>
            </div>

            <div id="reader" class="overflow-hidden rounded-2xl"></div>

            <button type="button" onclick="stopCamera()"
                class="w-full mt-4 bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium py-3 rounded-xl transition">
                Stop Camera
            </button>
        </div>

        <div class="bg-slate-900 rounded-2xl p-4 border border-slate-800">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-blue-500/15 flex items-center justify-center">
                    <i data-lucide="upload" class="w-5 h-5 text-blue-400"></i>
                </div>

                <div>
                    <p class="text-white font-semibold">Upload QR Screenshot</p>
                    <p class="text-slate-400 text-xs">Upload image file containing GX QR</p>
                </div>
            </div>

            <label class="block w-full cursor-pointer bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 rounded-xl transition text-center">
                Choose QR Image
                <input type="file" id="qrFileInput" accept="image/*" class="hidden">
            </label>

            <p id="fileName" class="text-slate-500 text-xs mt-3 text-center"></p>
        </div>

        <div class="bg-slate-900 rounded-2xl p-4 border border-slate-800 mt-4">
            <p class="text-slate-300 font-medium text-sm mb-2">Supported QR Format</p>

            <div class="space-y-2 text-xs">
                <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <p class="text-emerald-400 font-medium">Normal Account QR</p>
                    <p class="text-slate-500 mt-1">GXACC|account_number</p>
                </div>

                <div class="bg-slate-950 rounded-xl p-3 border border-slate-800">
                    <p class="text-blue-400 font-medium">Payment Request QR</p>
                    <p class="text-slate-500 mt-1">GXREQ|account_number|amount|reason</p>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
let html5QrCode = null;
let isScanning = false;
let alreadyRedirecting = false;

function showError(message) {
    const errorBox = document.getElementById("errorBox");
    const successBox = document.getElementById("successBox");

    successBox.classList.add("hidden");
    errorBox.innerText = message;
    errorBox.classList.remove("hidden");
}

function showSuccess(message) {
    const errorBox = document.getElementById("errorBox");
    const successBox = document.getElementById("successBox");

    errorBox.classList.add("hidden");
    successBox.innerText = message;
    successBox.classList.remove("hidden");
}

function extractQRData(qrText) {
    if (!qrText) {
        return null;
    }

    qrText = qrText.trim();

    if (qrText.startsWith("GXREQ|")) {
        const parts = qrText.split("|");

        if (parts.length >= 4) {
            const accountNumber = parts[1].trim();
            const amount = parts[2].trim();
            const reason = parts.slice(3).join("|").trim();

            if (!/^[0-9]+$/.test(accountNumber)) {
                return null;
            }

            if (isNaN(parseFloat(amount)) || parseFloat(amount) <= 0) {
                return null;
            }

            if (reason == "") {
                return null;
            }

            return {
                type: "request",
                accountNumber: accountNumber,
                amount: amount,
                reason: reason
            };
        }
    }

    if (qrText.startsWith("GXACC|")) {
        const accountNumber = qrText.replace("GXACC|", "").trim();

        if (!/^[0-9]+$/.test(accountNumber)) {
            return null;
        }

        return {
            type: "account",
            accountNumber: accountNumber,
            amount: "",
            reason: ""
        };
    }

    if (/^[0-9]+$/.test(qrText)) {
        return {
            type: "account",
            accountNumber: qrText,
            amount: "",
            reason: ""
        };
    }

    return null;
}

function handleQrResult(decodedText) {
    if (alreadyRedirecting) {
        return;
    }

    const data = extractQRData(decodedText);

    if (!data || !data.accountNumber) {
        showError("Invalid GX QR code.");
        return;
    }

    alreadyRedirecting = true;

    showSuccess("QR detected. Opening QR payment page...");

    let url = "/gxbank_html_by_page/pages/qr-pay.php?recipient_account_number=" + encodeURIComponent(data.accountNumber);

    if (data.type === "request") {
        url += "&requested_amount=" + encodeURIComponent(data.amount);
        url += "&request_reason=" + encodeURIComponent(data.reason);
        url += "&payment_request=1";
    }

    setTimeout(function() {
        window.location.href = url;
    }, 500);
}

function startCamera() {
    html5QrCode = new Html5Qrcode("reader");

    html5QrCode.start(
        { facingMode: "environment" },
        {
            fps: 10,
            qrbox: { width: 250, height: 250 }
        },
        function(decodedText) {
            if (isScanning && !alreadyRedirecting) {
                isScanning = false;

                if (html5QrCode) {
                    html5QrCode.stop().then(function() {
                        handleQrResult(decodedText);
                    }).catch(function() {
                        handleQrResult(decodedText);
                    });
                } else {
                    handleQrResult(decodedText);
                }
            }
        },
        function() {}
    ).then(function() {
        isScanning = true;
    }).catch(function() {
        showError("Unable to start camera. You can upload QR screenshot instead.");
    });
}

function stopCamera() {
    if (html5QrCode && isScanning) {
        html5QrCode.stop().then(function() {
            isScanning = false;
            showSuccess("Camera stopped.");
        }).catch(function() {
            isScanning = false;
        });
    }
}

document.getElementById("qrFileInput").addEventListener("change", function(event) {
    const file = event.target.files[0];
    const fileName = document.getElementById("fileName");

    if (!file) {
        return;
    }

    fileName.innerText = file.name;

    if (html5QrCode && isScanning) {
        html5QrCode.stop().then(function() {
            isScanning = false;
            scanUploadedFile(file);
        }).catch(function() {
            isScanning = false;
            scanUploadedFile(file);
        });
    } else {
        scanUploadedFile(file);
    }
});

function scanUploadedFile(file) {
    const qrScanner = new Html5Qrcode("reader");

    qrScanner.scanFile(file, true)
        .then(function(decodedText) {
            handleQrResult(decodedText);
        })
        .catch(function() {
            showError("Cannot read QR from this image. Try a clearer screenshot.");
        });
}

startCamera();
lucide.createIcons();
</script>

</body>
</html>