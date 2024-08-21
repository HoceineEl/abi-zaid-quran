import QrScanner from 'qr-scanner';

document.addEventListener('DOMContentLoaded', (event) => {
  const video = document.getElementById('qr-video');
  const camList = document.getElementById('cam-list');
  const inversionModeSelect = document.getElementById('inversion-mode-select');
  const startButton = document.getElementById('start-button');
  const stopButton = document.getElementById('stop-button');
  const scannedMemorizerIdInput = document.querySelector('input[wire\\:model\\.live="scannedMemorizerId"]');

  function setResult(result) {
    try {
      document.createElement('input').value = result.data;
      const data = JSON.parse(result.data);
      if (data && data.memorizer_id) {
        scannedMemorizerIdInput.value = data.memorizer_id;
        scannedMemorizerIdInput.dispatchEvent(new Event('input'));
        document.getElementById('scannedMemorizerId').value = data.memorizer_id;
      }
    } catch (error) {
      console.error('Invalid QR Code', error);
    }
  }

  const scanner = new QrScanner(video, result => setResult(result), {
    onDecodeError: error => {
      console.error(error);
    },
    highlightScanRegion: true,
    highlightCodeOutline: true,
  });

  startButton.addEventListener('click', () => {
    scanner.start();
  });

  stopButton.addEventListener('click', () => {
    scanner.stop();
  });

  QrScanner.listCameras(true).then(cameras => cameras.forEach(camera => {
    const option = document.createElement('option');
    option.value = camera.id;
    option.text = camera.label;
    camList.add(option);
  }));

  camList.addEventListener('change', event => {
    scanner.setCamera(event.target.value);
  });

  inversionModeSelect.addEventListener('change', event => {
    scanner.setInversionMode(event.target.value);
  });

  // Start scanner by default
  scanner.start();
});