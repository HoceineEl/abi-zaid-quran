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
                const monthNames = ['يناير', 'فبراير', 'مارس', 'إبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس',
                    'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
                ];
                const dayName = dayNames[date.getDay()];
                const monthName = monthNames[date.getMonth()];
                const formattedDate = `${dayName} ${date.getDate()} ${monthName}, ${date.getFullYear()}`;

                // Add title with formatted date
                const title = document.createElement('h2');
                title.textContent = `تقرير حضور الطلاب - ${formattedDate}`;
                title.style.textAlign = 'center';
                title.style.marginBottom = '20px';
                title.style.fontFamily = 'Changa, sans-serif';

                // Add group name if available
                if (data[0].groupName) {
                    const groupTitle = document.createElement('h3');
                    groupTitle.textContent = data[0].groupName;
                    groupTitle.style.textAlign = 'center';
                    groupTitle.style.marginBottom = '15px';
                    groupTitle.style.fontFamily = 'Changa, sans-serif';
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
                    const fileName = data[0].groupName ?
                        `تقرير-حضور-${data[0].groupName}-${formattedDate}.png` :
                        `تقرير-الحضور-${formattedDate}.png`;

                    // Create a blob from canvas
                    canvas.toBlob(function(blob) {
                        // Create share button container
                        const shareContainer = document.createElement('div');
                        shareContainer.style.position = 'fixed';
                        shareContainer.style.bottom = '20px';
                        shareContainer.style.right = '20px';
                        shareContainer.style.zIndex = '9999';
                        shareContainer.style.backgroundColor = 'white';
                        shareContainer.style.padding = '15px';
                        shareContainer.style.borderRadius = '8px';
                        shareContainer.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
                        shareContainer.style.display = 'flex';
                        shareContainer.style.alignItems = 'center';
                        shareContainer.style.gap = '10px';
                        shareContainer.style.fontFamily = 'Changa, sans-serif';
                        shareContainer.style.direction = 'rtl';

                        // Create share button
                        const shareButton = document.createElement('button');
                        shareButton.innerHTML = '<i class="fas fa-share-alt"></i> مشاركة التقرير';
                        shareButton.style.backgroundColor = '#3B82F6';
                        shareButton.style.color = 'white';
                        shareButton.style.border = 'none';
                        shareButton.style.padding = '8px 16px';
                        shareButton.style.borderRadius = '6px';
                        shareButton.style.cursor = 'pointer';
                        shareButton.style.display = 'flex';
                        shareButton.style.alignItems = 'center';
                        shareButton.style.gap = '8px';
                        shareButton.style.fontSize = '14px';

                        // Create close button
                        const closeButton = document.createElement('button');
                        closeButton.innerHTML = '×';
                        closeButton.style.backgroundColor = '#f3f4f6';
                        closeButton.style.border = 'none';
                        closeButton.style.borderRadius = '50%';
                        closeButton.style.width = '24px';
                        closeButton.style.height = '24px';
                        closeButton.style.cursor = 'pointer';
                        closeButton.style.display = 'flex';
                        closeButton.style.alignItems = 'center';
                        closeButton.style.justifyContent = 'center';

                        // Add click handlers
                        shareButton.onclick = function() {
                            const file = new File([blob], fileName, {
                                type: 'image/png'
                            });

                            if (navigator.share && navigator.canShare({
                                    files: [file]
                                })) {
                                navigator.share({
                                    files: [file],
                                    title: data[0].groupName ?
                                        `تقرير حضور ${data[0].groupName}` :
                                        'تقرير الحضور',
                                }).catch((error) => console.log('Error sharing:', error));
                            }
                        };

                        closeButton.onclick = function() {
                            document.body.removeChild(shareContainer);
                        };

                        // Assemble and add to page
                        shareContainer.appendChild(shareButton);
                        shareContainer.appendChild(closeButton);
                        document.body.appendChild(shareContainer);

                        // Auto-remove after 30 seconds
                        setTimeout(() => {
                            if (document.body.contains(shareContainer)) {
                                document.body.removeChild(shareContainer);
                            }
                        }, 30000);
                    });

                    // Clean up
                    document.body.removeChild(wrapper);
                    document.body.removeChild(container);
                });
            });
        });
    </script>
@endPushOnce
