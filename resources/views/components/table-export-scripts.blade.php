@pushOnce('scripts')
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700&display=swap" rel="stylesheet">
    <script>
        document.addEventListener('livewire:initialized', () => {
            const isDarkMode = () => document.documentElement.classList.contains('dark');

            function getFormattedDates() {
                const date = new Date();
                if (date.getHours() >= 0 && date.getHours() < 4) {
                    date.setDate(date.getDate() - 1);
                }
                const dayNames = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                const monthNames = ['يناير', 'فبراير', 'مارس', 'إبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
                const dayName = dayNames[date.getDay()];
                const monthName = monthNames[date.getMonth()];
                const formattedDate = `${dayName} ${date.getDate()} ${monthName}, ${date.getFullYear()}`;
                const hijriDate = new Intl.DateTimeFormat('ar-SA-u-ca-islamic', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }).format(date);
                return { formattedDate, hijriDate };
            }

            function buildPageWrapper(page, groupData, totalPages, dates, dark, imageFormat) {
                const wrapper = document.createElement('div');
                wrapper.style.background = dark ? '#111827' : 'white';
                wrapper.style.padding = '20px';
                wrapper.style.direction = 'rtl';
                wrapper.style.width = '800px';
                wrapper.style.fontFamily = 'Almarai, sans-serif';
                wrapper.style.color = dark ? '#f9fafb' : '#1f2937';
                wrapper.setAttribute('data-theme', dark ? 'dark' : 'light');

                // Title with dates
                const title = document.createElement('div');
                title.style.textAlign = 'center';
                title.style.marginBottom = '20px';
                title.style.fontFamily = 'Almarai, sans-serif';
                title.style.color = dark ? '#f9fafb' : '#1f2937';

                const hijriTitle = document.createElement('h2');
                const pageNumber = page.getAttribute('data-page');
                hijriTitle.textContent = `تقرير حضور الطلاب - ${dates.hijriDate}`;
                if (totalPages > 1) {
                    hijriTitle.textContent += ` - صفحة ${pageNumber}`;
                }
                hijriTitle.style.fontSize = '1.5rem';
                hijriTitle.style.marginBottom = '5px';

                const georgianTitle = document.createElement('h3');
                georgianTitle.textContent = dates.formattedDate;
                georgianTitle.style.fontSize = '1.2rem';
                georgianTitle.style.color = dark ? '#9ca3af' : '#6b7280';

                title.appendChild(hijriTitle);
                title.appendChild(georgianTitle);
                wrapper.appendChild(title);

                // Group name
                if (groupData.groupName) {
                    const groupTitle = document.createElement('h3');
                    groupTitle.textContent = groupData.groupName;
                    groupTitle.style.textAlign = 'center';
                    groupTitle.style.marginBottom = '15px';
                    groupTitle.style.fontSize = '1.8rem';
                    groupTitle.style.fontFamily = 'Almarai, sans-serif';
                    groupTitle.style.color = dark ? '#f9fafb' : '#1f2937';
                    wrapper.appendChild(groupTitle);
                }

                // Presence percentage
                const percentage = parseInt(groupData.presencePercentage);
                let percentageColor;
                if (percentage < 30) {
                    percentageColor = dark ? '#EF4444' : '#DC2626';
                } else if (percentage < 60) {
                    percentageColor = dark ? '#F59E0B' : '#D97706';
                } else if (percentage < 80) {
                    percentageColor = dark ? '#34D399' : '#10B981';
                } else {
                    percentageColor = dark ? '#10B981' : '#047857';
                }

                // Attendance display container
                const attendanceContainer = document.createElement('div');
                attendanceContainer.style.margin = '15px auto 25px';
                attendanceContainer.style.padding = '15px';
                attendanceContainer.style.borderRadius = '10px';
                attendanceContainer.style.backgroundColor = dark ? 'rgba(31, 41, 55, 0.6)' : 'rgba(249, 250, 251, 0.8)';
                attendanceContainer.style.boxShadow = dark ? '0 4px 6px -1px rgba(0, 0, 0, 0.2)' : '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
                attendanceContainer.style.display = 'flex';
                attendanceContainer.style.justifyContent = 'center';
                attendanceContainer.style.alignItems = 'center';
                attendanceContainer.style.flexWrap = 'wrap';
                attendanceContainer.style.gap = '30px';
                attendanceContainer.style.maxWidth = '700px';

                // Percentage indicator
                const percentageIndicator = document.createElement('div');
                percentageIndicator.style.position = 'relative';
                percentageIndicator.style.minWidth = '120px';
                percentageIndicator.style.textAlign = 'center';

                const percentageDisplay = document.createElement('div');
                percentageDisplay.textContent = `${percentage}%`;
                percentageDisplay.style.fontSize = '2.5rem';
                percentageDisplay.style.fontWeight = 'bold';
                percentageDisplay.style.color = percentageColor;
                percentageDisplay.style.marginBottom = '5px';

                const progressBar = document.createElement('div');
                progressBar.style.width = '100%';
                progressBar.style.height = '8px';
                progressBar.style.backgroundColor = dark ? '#4B5563' : '#E5E7EB';
                progressBar.style.borderRadius = '4px';
                progressBar.style.overflow = 'hidden';

                const progressFill = document.createElement('div');
                progressFill.style.height = '100%';
                progressFill.style.width = `${percentage}%`;
                progressFill.style.backgroundColor = percentageColor;
                progressFill.style.borderRadius = '4px';
                progressBar.appendChild(progressFill);

                percentageIndicator.appendChild(percentageDisplay);
                percentageIndicator.appendChild(progressBar);

                // Attendance info
                const attendanceInfo = document.createElement('div');
                attendanceInfo.style.textAlign = 'right';
                attendanceInfo.style.flex = '1';
                attendanceInfo.style.minWidth = '250px';

                const attendanceTitle = document.createElement('h3');
                attendanceTitle.textContent = 'نسبة الحضور';
                attendanceTitle.style.fontSize = '1.5rem';
                attendanceTitle.style.fontWeight = 'bold';
                attendanceTitle.style.marginBottom = '8px';
                attendanceTitle.style.color = dark ? '#f9fafb' : '#1f2937';

                const statusText = document.createElement('p');
                let statusMessage, statusIcon;
                if (percentage < 80) {
                    statusMessage = 'حضور منخفض ! يُرجى تفادي الغياب بغير عذر !';
                    statusIcon = '🔴';
                } else if (percentage < 100) {
                    statusMessage = 'حضور لا بأس به، نطمح إلى حضور شامل ومتميز !';
                    statusIcon = '🔄';
                } else {
                    statusMessage = 'حضور متميز ومشرف ! واصلوا حفظكم الله';
                    statusIcon = '🌟';
                }
                statusText.innerHTML = `${statusIcon} ${statusMessage}`;
                statusText.style.fontSize = '1.4rem';
                statusText.style.margin = '0';
                statusText.style.color = percentageColor;

                const dateRange = document.createElement('p');
                dateRange.textContent = groupData.dateRange || 'اليوم';
                dateRange.style.fontSize = '0.9rem';
                dateRange.style.color = dark ? '#9CA3AF' : '#6B7280';
                dateRange.style.marginTop = '5px';

                attendanceInfo.appendChild(attendanceTitle);
                attendanceInfo.appendChild(statusText);
                attendanceInfo.appendChild(dateRange);

                attendanceContainer.appendChild(percentageIndicator);
                attendanceContainer.appendChild(attendanceInfo);
                wrapper.appendChild(attendanceContainer);

                // 100% congrats
                if (percentage === 100) {
                    const congratsContainer = document.createElement('div');
                    congratsContainer.style.margin = '15px auto 20px';
                    congratsContainer.style.textAlign = 'center';
                    congratsContainer.style.padding = '15px';
                    congratsContainer.style.borderRadius = '10px';
                    congratsContainer.style.backgroundColor = dark ? 'rgba(16, 185, 129, 0.15)' : 'rgba(4, 120, 87, 0.08)';
                    congratsContainer.style.maxWidth = '550px';
                    congratsContainer.style.border = dark ? '2px solid rgba(52, 211, 153, 0.5)' : '2px solid rgba(4, 120, 87, 0.2)';
                    congratsContainer.style.boxShadow = dark ? '0 10px 15px -3px rgba(0, 0, 0, 0.3)' : '0 10px 15px -3px rgba(0, 0, 0, 0.1)';

                    const congratsMessage = document.createElement('div');
                    congratsMessage.textContent = 'بارك الله في هذه المجموعة المتميزة';
                    congratsMessage.style.fontSize = '1.5rem';
                    congratsMessage.style.fontWeight = 'bold';
                    congratsMessage.style.color = dark ? '#34D399' : '#047857';
                    congratsMessage.style.marginBottom = '15px';
                    congratsMessage.style.textShadow = dark ? '0 0 8px rgba(52, 211, 153, 0.5)' : '0 0 8px rgba(4, 120, 87, 0.2)';

                    const medalIcon = document.createElement('div');
                    medalIcon.textContent = '🥇';
                    medalIcon.style.fontSize = '5.5rem';
                    medalIcon.style.lineHeight = '1';
                    medalIcon.style.margin = '0 auto';

                    congratsContainer.appendChild(congratsMessage);
                    congratsContainer.appendChild(medalIcon);
                    wrapper.appendChild(congratsContainer);
                }

                // Table page content
                wrapper.appendChild(page.cloneNode(true));

                // Footer
                const footer = document.createElement('div');
                footer.style.marginTop = '20px';
                footer.style.textAlign = 'left';
                footer.style.fontSize = '12px';
                footer.style.color = dark ? '#9ca3af' : '#666';
                footer.style.fontFamily = 'Almarai, sans-serif';
                footer.textContent = `تم التصدير في: ${dates.formattedDate}`;
                wrapper.appendChild(footer);

                return wrapper;
            }

            /**
             * Generate image blobs for a single group's data.
             * @param {Object} groupData - { html, groupName, presencePercentage, dateRange }
             * @param {string} imageFormat - 'image/png' or 'image/jpeg'
             * @param {number} imageQuality - 0-1, only for JPEG
             * @returns {Promise<{blobs: Blob[], groupName: string}>}
             */
            async function generateGroupImages(groupData, imageFormat = 'image/png', imageQuality = 1) {
                const dark = isDarkMode();
                const dates = getFormattedDates();

                const container = document.createElement('div');
                container.style.position = 'absolute';
                container.style.left = '-9999px';
                container.innerHTML = groupData.html;
                container.setAttribute('data-theme', dark ? 'dark' : 'light');
                document.body.appendChild(container);

                const tablePages = container.querySelectorAll('.table-page');
                const blobs = [];

                for (const page of tablePages) {
                    const wrapper = buildPageWrapper(page, groupData, tablePages.length, dates, dark, imageFormat);
                    document.body.appendChild(wrapper);

                    const canvas = await html2canvas(wrapper, {
                        scale: 2,
                        backgroundColor: dark ? '#111827' : '#ffffff',
                        useCORS: true,
                        logging: false,
                        windowWidth: 800,
                    });

                    const blob = await new Promise(resolve => {
                        if (imageFormat === 'image/jpeg') {
                            canvas.toBlob(resolve, 'image/jpeg', imageQuality);
                        } else {
                            canvas.toBlob(resolve);
                        }
                    });
                    blobs.push(blob);

                    document.body.removeChild(wrapper);
                }

                document.body.removeChild(container);
                return { blobs, groupName: groupData.groupName };
            }

            function showShareNotification(allBlobs, groupNames) {
                const dark = isDarkMode();
                const totalImages = allBlobs.length;
                const isMultiGroup = groupNames.length > 1;

                // Remove any existing share container
                const existing = document.getElementById('export-share-container');
                if (existing) existing.remove();

                const shareContainer = document.createElement('div');
                shareContainer.id = 'export-share-container';
                shareContainer.style.position = 'fixed';
                shareContainer.style.bottom = '20px';
                shareContainer.style.right = '20px';
                shareContainer.style.zIndex = '9999';
                shareContainer.style.backgroundColor = dark ? '#1f2937' : 'white';
                shareContainer.style.padding = '15px';
                shareContainer.style.borderRadius = '8px';
                shareContainer.style.boxShadow = dark ? '0 2px 10px rgba(0,0,0,0.5)' : '0 2px 10px rgba(0,0,0,0.1)';
                shareContainer.style.display = 'flex';
                shareContainer.style.alignItems = 'center';
                shareContainer.style.gap = '10px';
                shareContainer.style.fontFamily = 'Almarai, sans-serif';
                shareContainer.style.direction = 'rtl';

                const shareButton = document.createElement('button');
                const label = isMultiGroup
                    ? `مشاركة تقارير ${groupNames.length} مجموعات (${totalImages} صورة)`
                    : `مشاركة التقرير ${totalImages > 1 ? `(${totalImages} صفحات)` : ''}`;
                shareButton.innerHTML = `
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                    </svg>
                    ${label}
                `;
                shareButton.style.backgroundColor = dark ? '#4f46e5' : '#3B82F6';
                shareButton.style.color = 'white';
                shareButton.style.border = 'none';
                shareButton.style.padding = '8px 16px';
                shareButton.style.borderRadius = '6px';
                shareButton.style.cursor = 'pointer';
                shareButton.style.display = 'flex';
                shareButton.style.alignItems = 'center';
                shareButton.style.gap = '8px';
                shareButton.style.fontSize = '14px';

                const closeButton = document.createElement('button');
                closeButton.innerHTML = '&times;';
                closeButton.style.backgroundColor = dark ? '#374151' : '#f3f4f6';
                closeButton.style.border = 'none';
                closeButton.style.borderRadius = '50%';
                closeButton.style.width = '24px';
                closeButton.style.height = '24px';
                closeButton.style.cursor = 'pointer';
                closeButton.style.display = 'flex';
                closeButton.style.alignItems = 'center';
                closeButton.style.justifyContent = 'center';
                closeButton.style.color = dark ? '#f9fafb' : '#1f2937';

                const ext = allBlobs[0]?.type === 'image/jpeg' ? 'jpg' : 'png';
                const mimeType = allBlobs[0]?.type || 'image/png';

                shareButton.onclick = async function() {
                    const files = allBlobs.map((blob, index) => {
                        // Determine which group this blob belongs to
                        const fileName = isMultiGroup
                            ? `تقرير-حضور-${index + 1}.${ext}`
                            : `تقرير-حضور-${groupNames[0]}-صفحة-${index + 1}.${ext}`;
                        return new File([blob], fileName, { type: mimeType });
                    });

                    if (navigator.share && navigator.canShare({ files })) {
                        try {
                            await navigator.share({
                                files,
                                title: isMultiGroup ? 'تقارير حضور المجموعات' : `تقرير حضور ${groupNames[0]}`,
                            });
                        } catch (error) {
                            console.log('Error sharing:', error);
                        }
                    }
                };

                closeButton.onclick = function() {
                    shareContainer.remove();
                };

                shareContainer.appendChild(shareButton);
                shareContainer.appendChild(closeButton);
                document.body.appendChild(shareContainer);

                setTimeout(() => {
                    if (document.body.contains(shareContainer)) {
                        shareContainer.remove();
                    }
                }, 60000);
            }

            // Single group export (existing behavior)
            Livewire.on('export-table', async (data) => {
                const groupData = data[0];
                const { blobs } = await generateGroupImages(groupData);
                showShareNotification(blobs, [groupData.groupName]);
            });

            // ── Daily Attendance Summary image export ─────────────────────────────

            function buildDailySummaryWrapper(page, summaryData, totalPages, pageNum, dates, dark) {
                const wrapper = document.createElement('div');
                wrapper.style.cssText = `
                    background: ${dark ? '#0f172a' : '#f8fafc'};
                    padding: 24px;
                    direction: rtl;
                    width: 960px;
                    font-family: Almarai, sans-serif;
                    color: ${dark ? '#f1f5f9' : '#1e293b'};
                `;
                wrapper.setAttribute('data-theme', dark ? 'dark' : 'light');

                // ── Header ──────────────────────────────────────────────────────────
                const header = document.createElement('div');
                header.style.cssText = `
                    background: linear-gradient(135deg, #1e3a5f 0%, #1d4ed8 100%);
                    padding: 18px 24px;
                    border-radius: 12px;
                    margin-bottom: 18px;
                    text-align: center;
                    color: white;
                    box-shadow: 0 4px 12px rgba(29,78,216,0.35);
                `;

                const mainTitle = document.createElement('h2');
                mainTitle.style.cssText = 'font-size:1.45rem; font-weight:bold; margin:0 0 4px;';
                mainTitle.textContent = `تقرير الحضور ليوم: ${summaryData.formattedDate}`;
                if (totalPages > 1) mainTitle.textContent += ` — صفحة ${pageNum} / ${totalPages}`;

                const hijriEl = document.createElement('p');
                hijriEl.style.cssText = 'font-size:0.9rem; opacity:0.75; margin:0;';
                hijriEl.textContent = dates.hijriDate;

                header.appendChild(mainTitle);
                header.appendChild(hijriEl);
                wrapper.appendChild(header);

                // ── Summary stat cards ───────────────────────────────────────────────
                const t = summaryData.totals;

                const statsRow = document.createElement('div');
                statsRow.style.cssText = `
                    display: flex;
                    gap: 10px;
                    margin-bottom: 18px;
                    flex-wrap: nowrap;
                `;

                const statCards = [
                    {
                        label : 'إجمالي الطلاب',
                        value : String(t.total_students),
                        pct   : null,
                        color : dark ? '#60a5fa' : '#1d4ed8',
                        bg    : dark ? 'rgba(29,78,216,0.18)' : '#dbeafe',
                        border: dark ? 'rgba(96,165,250,0.35)' : '#bfdbfe',
                    },
                    {
                        label : 'حاضر',
                        value : String(t.present),
                        pct   : t.present_pct,
                        color : dark ? '#4ade80' : '#15803d',
                        bg    : dark ? 'rgba(21,128,61,0.18)' : '#dcfce7',
                        border: dark ? 'rgba(74,222,128,0.35)' : '#bbf7d0',
                    },
                    {
                        label : 'غائب',
                        value : String(t.absent),
                        pct   : t.absent_pct,
                        color : dark ? '#f87171' : '#b91c1c',
                        bg    : dark ? 'rgba(185,28,28,0.18)' : '#fee2e2',
                        border: dark ? 'rgba(248,113,113,0.35)' : '#fecaca',
                    },
                    {
                        label : 'غائب بعذر',
                        value : String(t.absent_with_reason),
                        pct   : t.absent_reason_pct,
                        color : dark ? '#fbbf24' : '#b45309',
                        bg    : dark ? 'rgba(180,83,9,0.18)' : '#fef3c7',
                        border: dark ? 'rgba(251,191,36,0.35)' : '#fde68a',
                    },
                    {
                        label : 'لم يحدد',
                        value : String(t.not_specified),
                        pct   : t.not_specified_pct,
                        color : dark ? '#94a3b8' : '#475569',
                        bg    : dark ? 'rgba(71,85,105,0.25)' : '#f1f5f9',
                        border: dark ? 'rgba(148,163,184,0.30)' : '#e2e8f0',
                    },
                ];

                statCards.forEach(card => {
                    const cardEl = document.createElement('div');
                    cardEl.style.cssText = `
                        flex: 1;
                        background: ${card.bg};
                        border: 1px solid ${card.border};
                        border-radius: 10px;
                        padding: 12px 8px;
                        text-align: center;
                    `;

                    const valEl = document.createElement('div');
                    valEl.style.cssText = `font-size:1.4rem; font-weight:800; color:${card.color}; line-height:1.1;`;
                    valEl.textContent = card.value;

                    if (card.pct !== null) {
                        const pctEl = document.createElement('span');
                        pctEl.style.cssText = `font-size:0.75rem; font-weight:400; opacity:0.70; margin-right:3px;`;
                        pctEl.textContent = ` ${card.pct}%`;
                        valEl.appendChild(pctEl);
                    }

                    const labelEl = document.createElement('div');
                    labelEl.style.cssText = `font-size:0.7rem; color:${dark ? '#94a3b8' : '#64748b'}; margin-top:5px; font-weight:500;`;
                    labelEl.textContent = card.label;

                    cardEl.appendChild(valEl);
                    cardEl.appendChild(labelEl);
                    statsRow.appendChild(cardEl);
                });

                wrapper.appendChild(statsRow);

                // ── Table ────────────────────────────────────────────────────────────
                wrapper.appendChild(page.cloneNode(true));

                // ── Footer ───────────────────────────────────────────────────────────
                const footer = document.createElement('div');
                footer.style.cssText = `margin-top:14px; text-align:left; font-size:11px; color:${dark ? '#475569' : '#94a3b8'};`;
                footer.textContent = `تم التصدير في: ${dates.formattedDate}`;
                wrapper.appendChild(footer);

                return wrapper;
            }

            Livewire.on('export-daily-summary', async (data) => {
                const summaryData = data[0];
                const dark  = isDarkMode();
                const dates = getFormattedDates();

                const container = document.createElement('div');
                container.style.cssText = 'position:absolute; left:-9999px;';
                container.innerHTML = summaryData.html;
                container.setAttribute('data-theme', dark ? 'dark' : 'light');
                document.body.appendChild(container);

                const pages = container.querySelectorAll('.table-page');
                const blobs = [];

                for (const page of pages) {
                    const pageNum = page.getAttribute('data-page') || '1';
                    const wrapper = buildDailySummaryWrapper(page, summaryData, pages.length, pageNum, dates, dark);
                    document.body.appendChild(wrapper);

                    const canvas = await html2canvas(wrapper, {
                        scale:           2,
                        backgroundColor: dark ? '#0f172a' : '#f8fafc',
                        useCORS:         true,
                        logging:         false,
                        windowWidth:     960,
                    });

                    const blob = await new Promise(resolve => canvas.toBlob(resolve));
                    blobs.push(blob);
                    document.body.removeChild(wrapper);
                }

                document.body.removeChild(container);
                showShareNotification(blobs, ['موجز-الحضور-اليومي']);
            });

            // ── Bulk export for multiple groups ──────────────────────────────────
            Livewire.on('export-tables-bulk', async (data) => {
                const groups = data[0].groups;
                const allBlobs = [];
                const groupNames = [];

                // Show progress indicator
                const dark = isDarkMode();
                const progressEl = document.createElement('div');
                progressEl.id = 'bulk-export-progress';
                progressEl.style.position = 'fixed';
                progressEl.style.bottom = '20px';
                progressEl.style.right = '20px';
                progressEl.style.zIndex = '9999';
                progressEl.style.backgroundColor = dark ? '#1f2937' : 'white';
                progressEl.style.padding = '15px 20px';
                progressEl.style.borderRadius = '8px';
                progressEl.style.boxShadow = dark ? '0 2px 10px rgba(0,0,0,0.5)' : '0 2px 10px rgba(0,0,0,0.1)';
                progressEl.style.fontFamily = 'Almarai, sans-serif';
                progressEl.style.direction = 'rtl';
                progressEl.style.color = dark ? '#f9fafb' : '#1f2937';
                progressEl.style.fontSize = '14px';
                progressEl.style.display = 'flex';
                progressEl.style.alignItems = 'center';
                progressEl.style.gap = '10px';
                document.body.appendChild(progressEl);

                for (let i = 0; i < groups.length; i++) {
                    const groupData = groups[i];
                    progressEl.textContent = `جاري تصدير المجموعة ${i + 1} من ${groups.length}... (${groupData.groupName})`;

                    const { blobs, groupName } = await generateGroupImages(groupData, 'image/jpeg', 0.85);
                    allBlobs.push(...blobs);
                    groupNames.push(groupName);

                    // Small delay to keep UI responsive
                    await new Promise(r => setTimeout(r, 50));
                }

                // Remove progress indicator
                progressEl.remove();

                // Show share notification with all images
                showShareNotification(allBlobs, groupNames);
            });
        });
    </script>
@endPushOnce
