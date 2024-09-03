import QrScanner from 'qr-scanner';

let scanner = null;

function initQrScanner() {
  const video = document.getElementById('qr-video');
  const camList = document.getElementById('cam-list');
  const inversionModeSelect = document.getElementById('inversion-mode-select');
  const startButton = document.getElementById('start-button');
  const stopButton = document.getElementById('stop-button');

  if (!video || scanner) return;

  function setResult(result) {
    try {
      const data = JSON.parse(result.data);
      if (data && data.memorizer_id) {
        Livewire.dispatch('set-scanned-memorizer-id', { id: data.memorizer_id });
      }
    } catch (error) {
      console.error('Invalid QR Code', error);
    }
  }

  // Create QR scanner instance
  scanner = new QrScanner(video, result => setResult(result), {
    onDecodeError: error => {
      console.error(error);
    },
    highlightScanRegion: true,
    highlightCodeOutline: true,
  });

  // Start scanner
  scanner.start().then(() => {
    updateScannerState(true);
  });

  // Set up camera list
  QrScanner.listCameras(true).then(cameras => {
    cameras.forEach(camera => {
      const option = document.createElement('option');
      option.value = camera.id;
      option.text = camera.label;
      camList.add(option);
    });
  });

  // Event listeners
  startButton.addEventListener('click', () => {
    scanner.start().then(() => {
      updateScannerState(true);
    });
  });

  stopButton.addEventListener('click', () => {
    scanner.stop();
    updateScannerState(false);
  });

  camList.addEventListener('change', event => {
    scanner.setCamera(event.target.value);
  });

  inversionModeSelect.addEventListener('change', event => {
    scanner.setInversionMode(event.target.value);
  });
}

function updateScannerState(isScanning) {
  const startButton = document.getElementById('start-button');
  const stopButton = document.getElementById('stop-button');

  startButton.disabled = isScanning;
  stopButton.disabled = !isScanning;
}

function destroyQrScanner() {
  if (scanner) {
    scanner.stop();
    scanner.destroy();
    scanner = null;
  }
}

// Initialize scanner when the component is loaded
document.addEventListener('livewire:initialized', () => {
  initQrScanner();
});

// Reinitialize scanner when Livewire updates the component
document.addEventListener('livewire:navigated', () => {
  destroyQrScanner();
  initQrScanner();
});

// Cleanup when the component is removed
document.addEventListener('livewire:disconnected', () => {
  destroyQrScanner();
});

// Expose functions to window for potential external use
window.qrScanner = {
  init: initQrScanner,
  destroy: destroyQrScanner
};