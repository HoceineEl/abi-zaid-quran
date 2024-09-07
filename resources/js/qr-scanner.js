import QrScanner from 'qr-scanner';

let scanner = null;

function initQrScanner() {
  console.log('Initializing QR Scanner');
  const video = document.getElementById('qr-video');
  const camList = document.getElementById('cam-list');
  const inversionModeSelect = document.getElementById('inversion-mode-select');
  const startButton = document.getElementById('start-button');
  const stopButton = document.getElementById('stop-button');

  if (!video) {
    console.error('Video element not found');
    return;
  }

  if (scanner) {
    console.log('Scanner already initialized');
    return;
  }

  QrScanner.hasCamera().then(hasCamera => {
    Livewire.dispatch('camera-availability', { available: hasCamera });

    if (!hasCamera) {
      console.error('No camera found on this device');
      return;
    }

    scanner = new QrScanner(video, result => setResult(result), {
      onDecodeError: error => {
        console.warn('QR code scan error:', error);
      },
      highlightScanRegion: true,
      highlightCodeOutline: true,
    });

    scanner.start().then(() => {
      console.log('Scanner started');
      updateScannerState(true);
    }).catch(err => {
      console.error('Failed to start scanner:', err);
      // Display a user-friendly error message on the page
      document.getElementById('video-container').innerHTML = `<p>Failed to start the scanner. Error: ${err.message}</p>`;
    });

    QrScanner.listCameras(true).then(cameras => {
      console.log('Available cameras:', cameras);
      camList.innerHTML = '';
      cameras.forEach(camera => {
        const option = document.createElement('option');
        option.value = camera.id;
        option.text = camera.label;
        camList.add(option);
      });
    });

    startButton.addEventListener('click', () => {
      console.log('Start button clicked');
      scanner.start().then(() => {
        updateScannerState(true);
      }).catch(err => {
        console.error('Failed to start scanner:', err);
      });
    });

    stopButton.addEventListener('click', () => {
      console.log('Stop button clicked');
      scanner.stop();
      updateScannerState(false);
    });

    camList.addEventListener('change', event => {
      console.log('Camera changed:', event.target.value);
      scanner.setCamera(event.target.value);
    });

    inversionModeSelect.addEventListener('change', event => {
      console.log('Inversion mode changed:', event.target.value);
      scanner.setInversionMode(event.target.value);
    });
  });
}


function updateScannerState(isScanning) {
  const startButton = document.getElementById('start-button');
  const stopButton = document.getElementById('stop-button');

  startButton.disabled = isScanning;
  stopButton.disabled = !isScanning;
}

function destroyQrScanner() {
  console.log('Destroying QR Scanner');
  if (scanner) {
    scanner.stop();
    scanner.destroy();
    scanner = null;
  }
}

document.addEventListener('livewire:init', () => {
  Livewire.on('qr-scanner-mounted', () => {
    console.log('QR Scanner component mounted');
    initQrScanner();
  });
});

document.addEventListener('livewire:navigated', () => {
  console.log('Page navigated');
  destroyQrScanner();
  initQrScanner();
});

document.addEventListener('livewire:disconnected', () => {
  console.log('Livewire disconnected');
  destroyQrScanner();
});

window.qrScanner = {
  init: initQrScanner,
  destroy: destroyQrScanner
};