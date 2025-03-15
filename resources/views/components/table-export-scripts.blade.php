@pushOnce('scripts')
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700&display=swap" rel="stylesheet">
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('export-table', async (data) => {
                const isDarkMode = document.documentElement.classList.contains('dark');
                console.log(data);
                // Create temporary container
                const container = document.createElement('div');
                container.style.position = 'absolute';
                container.style.left = '-9999px';
                container.innerHTML = data[0].html;
                container.setAttribute('data-theme', isDarkMode ? 'dark' : 'light');
                document.body.appendChild(container);

                // Get all table pages
                const tablePages = container.querySelectorAll('.table-page');
                const blobs = [];

                // Process each page
                for (const page of tablePages) {
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
                    // Adjust date if it's between midnight and 4 AM
                    if (date.getHours() >= 0 && date.getHours() < 4) {
                        date.setDate(date.getDate() - 1);
                    }
                    const dayNames = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة',
                        'السبت'
                    ];
                    const monthNames = ['يناير', 'فبراير', 'مارس', 'إبريل', 'مايو', 'يونيو', 'يوليو',
                        'أغسطس',
                        'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
                    ];
                    const dayName = dayNames[date.getDay()];
                    const monthName = monthNames[date.getMonth()];
                    const formattedDate =
                        `${dayName} ${date.getDate()} ${monthName}, ${date.getFullYear()}`;

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
                    const pageNumber = page.getAttribute('data-page');
                    const totalPages = tablePages.length;
                    hijriTitle.textContent = `تقرير حضور الطلاب - ${hijriDate}`;
                    if (totalPages > 1) {
                        hijriTitle.textContent += ` - صفحة ${pageNumber}`;
                    }
                    hijriTitle.style.fontSize = '1.5rem';
                    hijriTitle.style.marginBottom = '5px';

                    const georgianTitle = document.createElement('h3');
                    georgianTitle.textContent = formattedDate;
                    georgianTitle.style.fontSize = '1.2rem';
                    georgianTitle.style.color = isDarkMode ? '#9ca3af' : '#6b7280';

                    title.appendChild(hijriTitle);
                    title.appendChild(georgianTitle);
                    wrapper.appendChild(title);

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
                    // Add presence percentage if available
                    const presenceTitle = document.createElement('h4');
                    presenceTitle.textContent = `نسبة الحضور: ${data[0].presencePercentage}%`;
                    presenceTitle.style.textAlign = 'center';
                    presenceTitle.style.marginBottom = '15px';
                    presenceTitle.style.fontSize = '1.4rem';
                    presenceTitle.style.fontFamily = 'Almarai, sans-serif';

                    // Set color based on percentage value
                    const percentage = parseInt(data[0].presencePercentage);
                    let percentageColor;

                    if (percentage < 30) {
                        // Red for low attendance
                        percentageColor = isDarkMode ? '#EF4444' : '#DC2626';
                    } else if (percentage < 60) {
                        // Orange/Yellow for medium attendance
                        percentageColor = isDarkMode ? '#F59E0B' : '#D97706';
                    } else if (percentage < 80) {
                        // Light green for good attendance
                        percentageColor = isDarkMode ? '#34D399' : '#10B981';
                    } else {
                        // Bright green for excellent attendance
                        percentageColor = isDarkMode ? '#10B981' : '#047857';
                    }

                    presenceTitle.style.color = percentageColor;
                    wrapper.appendChild(presenceTitle);

                    // Add the current page
                    wrapper.appendChild(page.cloneNode(true));

                    // Add footer
                    const footer = document.createElement('div');
                    footer.style.marginTop = '20px';
                    footer.style.textAlign = 'left';
                    footer.style.fontSize = '12px';
                    footer.style.color = isDarkMode ? '#9ca3af' : '#666';
                    footer.style.fontFamily = 'Almarai, sans-serif';
                    footer.textContent = `تم التصدير في: ${formattedDate}`;
                    wrapper.appendChild(footer);

                    document.body.appendChild(wrapper);

                    // Convert to image
                    const canvas = await html2canvas(wrapper, {
                        scale: 2,
                        backgroundColor: isDarkMode ? '#111827' : '#ffffff',
                        useCORS: true,
                        logging: false,
                        windowWidth: 800,
                    });

                    // Get blob
                    const blob = await new Promise(resolve => canvas.toBlob(resolve));
                    blobs.push(blob);

                    document.body.removeChild(wrapper);
                }

                // Clean up
                document.body.removeChild(container);

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
                    مشاركة التقرير ${blobs.length > 1 ? `(${blobs.length} صفحات)` : ''}
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
                shareButton.onclick = async function() {
                    const files = blobs.map((blob, index) => {
                        const fileName = data[0].groupName ?
                            `تقرير-حضور-${data[0].groupName}-صفحة-${index + 1}.png` :
                            `تقرير-الحضور-صفحة-${index + 1}.png`;
                        return new File([blob], fileName, {
                            type: 'image/png'
                        });
                    });

                    if (navigator.share && navigator.canShare({
                            files
                        })) {
                        try {
                            await navigator.share({
                                files,
                                title: data[0].groupName ?
                                    `تقرير حضور ${data[0].groupName}` : 'تقرير الحضور',
                            });
                        } catch (error) {
                            console.log('Error sharing:', error);
                        }
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
        });
    </script>
@endPushOnce
