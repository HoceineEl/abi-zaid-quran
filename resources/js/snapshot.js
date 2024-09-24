import html2canvas from 'html2canvas';

document.addEventListener('livewire:initialized', () => {
    Livewire.on('takeTableSnapshot', () => {
        const table = document.querySelector('.fi-ta-content');
        if (table) {
            console.error('Table element not found');
            return;
        }
        console.log('Table element found:', table);

        html2canvas(table).then(function (canvas) {
            console.log('Canvas created:', canvas);
            canvas.toBlob(function (blob) {
                if (!blob) {
                    console.error('Failed to create blob from canvas');
                    return;
                }
                console.log('Blob created:', blob);

                const file = new File([blob], "table-snapshot.png", { type: "image/png" });

                if (navigator.share) {
                    navigator.share({
                        files: [file],
                        title: 'Table Snapshot',
                        text: 'Check out this table snapshot!'
                    }).then(() => {
                        console.log('Share was successful.');
                        Livewire.dispatch('snapshotTaken');
                    }).catch((error) => {
                        console.error('Sharing failed', error);
                        Livewire.dispatch('snapshotFailed', { error: error.message });
                    });
                } else {
                    // Fallback for browsers that don't support sharing
                    const link = document.createElement('a');
                    link.download = 'table-snapshot.png';
                    link.href = URL.createObjectURL(blob);
                    link.click();
                    console.log('Download initiated');
                    Livewire.dispatch('snapshotTaken');
                }
            });
        }).catch(function (error) {
            console.error('html2canvas failed:', error);
            Livewire.dispatch('snapshotFailed', { error: error.message });
        });
    });
});