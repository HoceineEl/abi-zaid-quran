@pushOnce('scripts')
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700&display=swap" rel="stylesheet">
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('export-table', (data) => {
                // Check if html tag has dark class
                const isDarkMode = document.documentElement.classList.contains('dark');
                
                const container = document.createElement('div');
                container.style.position = 'absolute';
                container.style.left = '-9999px';
                container.innerHTML = data[0].html;
                container.setAttribute('data-theme', isDarkMode ? 'dark' : 'light');
                document.body.appendChild(container);

                // Create a wrapper div for custom styling
                const wrapper = document.createElement('div');
                wrapper.style.background = isDarkMode ? '#111827' : 'white';
                wrapper.style.padding = '20px';
                wrapper.style.direction = 'rtl';
                wrapper.style.width = '800px';
                wrapper.style.fontFamily = 'Almarai, sans-serif';
                wrapper.style.color = isDarkMode ? '#f9fafb' : '#1f2937';
                wrapper.setAttribute('data-theme', isDarkMode ? 'dark' : 'light');

                // Format Georgian date
                const date = new Date();
                const dayNames = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                const monthNames = ['يناير', 'فبراير', 'مارس', 'إبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس',
                    'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
                ];
                const dayName = dayNames[date.getDay()];
                const monthName = monthNames[date.getMonth()];
                const formattedDate = `${dayName} ${date.getDate()} ${monthName}, ${date.getFullYear()}`;

                // Format Hijri date
                const hijriDate = new Intl.DateTimeFormat('ar-SA-u-ca-islamic', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }).format(date);

                // Add title with both dates
                const title = document.createElement('div');
                title.style.textAlign = 'center';
                title.style.marginBottom = '20px';
                title.style.fontFamily = 'Almarai, sans-serif';
                title.style.color = isDarkMode ? '#f9fafb' : '#1f2937';

                const hijriTitle = document.createElement('h2');
                hijriTitle.textContent = `تقرير حضور الطلاب - ${hijriDate}`;
                hijriTitle.style.fontSize = '1.5rem';
                hijriTitle.style.marginBottom = '5px';

                const georgianTitle = document.createElement('h3');
                georgianTitle.textContent = formattedDate;
                georgianTitle.style.fontSize = '1.2rem';
                georgianTitle.style.color = isDarkMode ? '#9ca3af' : '#6b7280';

                title.appendChild(hijriTitle);
                title.appendChild(georgianTitle);

                // Add group name if available
                if (data[0].groupName) {
                    const groupTitle = document.createElement('h3');
                    groupTitle.textContent = data[0].groupName;
                    groupTitle.style.textAlign = 'center';
                    groupTitle.style.marginBottom = '15px';
                    groupTitle.style.fontSize = '1.8rem';
                    groupTitle.style.fontFamily = 'Almarai, sans-serif';
                    groupTitle.style.color = isDarkMode ? '#f9fafb' : '#1f2937';
                    wrapper.appendChild(groupTitle);
                }

                wrapper.appendChild(title);
                wrapper.appendChild(container.querySelector('.export-table-container'));

                // Add date and time footer
                const footer = document.createElement('div');
                footer.style.marginTop = '20px';
                footer.style.textAlign = 'left';
                footer.style.fontSize = '12px';
                footer.style.color = isDarkMode ? '#9ca3af' : '#666';
                footer.style.fontFamily = 'Almarai, sans-serif';
                footer.textContent = `تم التصدير في: ${formattedDate}`;
                wrapper.appendChild(footer);

                // Temporarily add wrapper to document
                document.body.appendChild(wrapper);
                
                // Convert to image
                html2canvas(wrapper, {
                    scale: 2,
                    backgroundColor: isDarkMode ? '#111827' : '#ffffff',
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
                        shareContainer.style.backgroundColor = isDarkMode ? '#1f2937' : 'white';
                        shareContainer.style.padding = '15px';
                        shareContainer.style.borderRadius = '8px';
                        shareContainer.style.boxShadow = isDarkMode ? 
                            '0 2px 10px rgba(0,0,0,0.5)' : 
                            '0 2px 10px rgba(0,0,0,0.1)';
                        shareContainer.style.display = 'flex';
                        shareContainer.style.alignItems = 'center';
                        shareContainer.style.gap = '10px';
                        shareContainer.style.fontFamily = 'Almarai, sans-serif';
                        shareContainer.style.direction = 'rtl';

                        // Create share button
                        const shareButton = document.createElement('button');
                        shareButton.innerHTML = `
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                            </svg>
                            مشاركة التقرير
                        `;
                        shareButton.style.backgroundColor = isDarkMode ? '#4f46e5' : '#3B82F6';
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
                        closeButton.style.backgroundColor = isDarkMode ? '#374151' : '#f3f4f6';
                        closeButton.style.border = 'none';
                        closeButton.style.borderRadius = '50%';
                        closeButton.style.width = '24px';
                        closeButton.style.height = '24px';
                        closeButton.style.cursor = 'pointer';
                        closeButton.style.display = 'flex';
                        closeButton.style.alignItems = 'center';
                        closeButton.style.justifyContent = 'center';
                        closeButton.style.color = isDarkMode ? '#f9fafb' : '#1f2937';

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
