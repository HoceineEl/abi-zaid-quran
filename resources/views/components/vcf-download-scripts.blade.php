@pushOnce('scripts')
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('download-vcf', (data) => {
                const content = data[0].content;
                const filename = data[0].filename;

                // Create blob with VCF content
                const blob = new Blob([content], {
                    type: 'text/vcard;charset=utf-8'
                });

                // Create download link
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = filename;

                // Trigger download
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Clean up
                URL.revokeObjectURL(link.href);
            });
        });
    </script>
@endPushOnce
