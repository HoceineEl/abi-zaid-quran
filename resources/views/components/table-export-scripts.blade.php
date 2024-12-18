@pushOnce('scripts')
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Changa:wght@400;500&display=swap" rel="stylesheet">
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('export-table', (data) => {
                const container = document.createElement('div');
                container.style.position = 'absolute';
                container.style.left = '-9999px';
                container.innerHTML = data[0].html;
                document.body.appendChild(container);

                // Create a wrapper div for custom styling
                const wrapper = document.createElement('div');
                wrapper.style.background = 'white';
                wrapper.style.padding = '20px';
                wrapper.style.direction = 'rtl';
                wrapper.style.width = '800px';
                wrapper.style.fontFamily = 'Changa, sans-serif';

                // Format date
                const date = new Date();
                const dayNames = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                const monthNames = ['يناير', 'فبراير', 'مارس', 'إبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
                const dayName = dayNames[date.getDay()];
                const monthName = monthNames[date.getMonth()];
                const formattedDate = `${dayName} ${date.getDate()} ${monthName}, ${date.getFullYear()}`;

                // Add title with formatted date
                const title = document.createElement('h2');
                title.textContent = `تقرير حضور الطلاب - ${formattedDate}`;
                title.style.textAlign = 'center';
                title.style.marginBottom = '20px';
                title.style.fontFamily = 'Changa, sans-serif';
                title.style.color = document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000';

                // Add group name if available
                if (data[0].groupName) {
                    const groupTitle = document.createElement('h3');
                    groupTitle.textContent = data[0].groupName;
                    groupTitle.style.textAlign = 'center';
                    groupTitle.style.marginBottom = '15px';
                    groupTitle.style.fontFamily = 'Changa, sans-serif';
                    groupTitle.style.color = document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000';
                    wrapper.appendChild(groupTitle);
                }

                wrapper.appendChild(title);
                wrapper.appendChild(container.querySelector('.export-table-container'));

                // Add date and time footer
                const footer = document.createElement('div');
                footer.style.marginTop = '20px';
                footer.style.textAlign = 'left';
                footer.style.fontSize = '12px';
                footer.style.color = '#666';
                footer.style.fontFamily = 'Changa, sans-serif';
                footer.textContent = `تم التصدير في: ${formattedDate}`;
                wrapper.appendChild(footer);

                // Temporarily add wrapper to document
                document.body.appendChild(wrapper);

                // Convert to image
                html2canvas(wrapper, {
                    scale: 2,
                    backgroundColor: '#ffffff',
                    useCORS: true,
                    logging: false,
                    windowWidth: 800,
                }).then(canvas => {
                    const fileName = data[0].groupName 
                        ? `تقرير-حضور-${data[0].groupName}-${formattedDate}.png`
                        : `تقرير-الحضور-${formattedDate}.png`;

                    // Create a blob from canvas
                    canvas.toBlob(function(blob) {
                        // Create a File from the Blob
                        const file = new File([blob], fileName, {
                            type: 'image/png'
                        });

                        // Try to share directly
                        if (navigator.share && navigator.canShare({files: [file]})) {
                            navigator.share({
                                files: [file],
                                title: data[0].groupName 
                                    ? `تقرير حضور ${data[0].groupName}`
                                    : 'تقرير الحضور',
                            }).catch((error) => {
                                console.log('Error sharing:', error);
                                // Fallback to WhatsApp share if direct share fails
                                const shareUrl = data[0].groupName
                                    ? `whatsapp://send?text=تقرير حضور ${data[0].groupName} - ${formattedDate}`
                                    : `whatsapp://send?text=تقرير الحضور ${formattedDate}`;
                                window.open(shareUrl);
                            });
                        } else {
                            // Fallback for browsers that don't support sharing files
                            const shareUrl = data[0].groupName
                                ? `whatsapp://send?text=تقرير حضور ${data[0].groupName} - ${formattedDate}`
                                : `whatsapp://send?text=تقرير الحضور ${formattedDate}`;
                            window.open(shareUrl);
                        }
                    });

                    // Clean up
                    document.body.removeChild(wrapper);
                    document.body.removeChild(container);
                });
            });
        });
    </script>
@endPushOnce
