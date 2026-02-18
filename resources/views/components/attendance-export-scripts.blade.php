@pushOnce('scripts')
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700&display=swap" rel="stylesheet">
    <script>
        document.addEventListener('livewire:initialized', () => {
            const isDarkMode = () => document.documentElement.classList.contains('dark');

            function getFormattedDates() {
                const date = new Date();
                const dayNames   = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                const monthNames = ['يناير', 'فبراير', 'مارس', 'إبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
                const formattedDate = `${dayNames[date.getDay()]} ${date.getDate()} ${monthNames[date.getMonth()]}, ${date.getFullYear()}`;
                const hijriDate = new Intl.DateTimeFormat('ar-SA-u-ca-islamic', {
                    day: 'numeric', month: 'long', year: 'numeric'
                }).format(date);
                return { formattedDate, hijriDate };
            }

            function buildPageWrapper(page, groupData, totalPages, dates, dark) {
                const wrapper = document.createElement('div');
                wrapper.style.background    = dark ? '#111827' : 'white';
                wrapper.style.padding       = '20px';
                wrapper.style.direction     = 'rtl';
                wrapper.style.width         = '800px';
                wrapper.style.fontFamily    = 'Almarai, sans-serif';
                wrapper.style.color         = dark ? '#f9fafb' : '#1f2937';
                wrapper.setAttribute('data-theme', dark ? 'dark' : 'light');

                // Header
                const header = document.createElement('div');
                header.style.textAlign    = 'center';
                header.style.marginBottom = '16px';

                const pageNum   = page.getAttribute('data-page');
                const mainTitle = document.createElement('h2');
                mainTitle.textContent  = `تقرير الحضور والتقييم - ${dates.hijriDate}`;
                if (totalPages > 1) mainTitle.textContent += ` (${pageNum}/${totalPages})`;
                mainTitle.style.fontSize     = '1.4rem';
                mainTitle.style.marginBottom = '4px';

                const subTitle = document.createElement('h3');
                subTitle.textContent = dates.formattedDate;
                subTitle.style.fontSize = '1rem';
                subTitle.style.color    = dark ? '#9ca3af' : '#6b7280';

                header.appendChild(mainTitle);
                header.appendChild(subTitle);
                wrapper.appendChild(header);

                // Group name
                if (groupData.groupName) {
                    const groupTitle = document.createElement('h3');
                    groupTitle.textContent   = groupData.groupName;
                    groupTitle.style.textAlign    = 'center';
                    groupTitle.style.marginBottom = '12px';
                    groupTitle.style.fontSize     = '1.6rem';
                    wrapper.appendChild(groupTitle);
                }

                // Presence percentage
                const pct = parseInt(groupData.presencePercentage ?? 0);
                const pctColor = pct < 30 ? (dark ? '#EF4444' : '#DC2626')
                               : pct < 60 ? (dark ? '#F59E0B' : '#D97706')
                               : pct < 80 ? (dark ? '#34D399' : '#10B981')
                               :            (dark ? '#10B981' : '#047857');

                const pctDiv = document.createElement('div');
                pctDiv.style.cssText = `text-align:center;margin-bottom:14px;font-size:1.05rem;font-weight:bold;color:${pctColor}`;
                pctDiv.textContent   = `نسبة الحضور: ${pct}%`;
                wrapper.appendChild(pctDiv);

                // Table — wrap in .table-page so the blade CSS selectors still match
                const tableContainer = document.createElement('div');
                tableContainer.className = 'table-page';
                const table = page.querySelector('table');
                if (table) tableContainer.appendChild(table.cloneNode(true));
                wrapper.appendChild(tableContainer);

                // Footer
                const footer = document.createElement('div');
                footer.style.cssText  = `margin-top:12px;text-align:left;font-size:11px;color:${dark ? '#9ca3af' : '#6b7280'}`;
                footer.textContent    = `تم التصدير في: ${new Date().toLocaleTimeString('ar-SA')}`;
                wrapper.appendChild(footer);

                return wrapper;
            }

            async function generateImages(groupData) {
                const dark  = isDarkMode();
                const dates = getFormattedDates();

                const container = document.createElement('div');
                container.style.position = 'absolute';
                container.style.left     = '-9999px';
                container.innerHTML      = groupData.html;
                container.setAttribute('data-theme', dark ? 'dark' : 'light');
                document.body.appendChild(container);

                const pages = container.querySelectorAll('.table-page');
                const blobs = [];

                for (const page of pages) {
                    const wrapper = buildPageWrapper(page, groupData, pages.length, dates, dark);
                    document.body.appendChild(wrapper);

                    const canvas = await html2canvas(wrapper, {
                        scale:           2,
                        backgroundColor: dark ? '#111827' : '#ffffff',
                        useCORS:         true,
                        logging:         false,
                        windowWidth:     800,
                    });

                    const blob = await new Promise(resolve => canvas.toBlob(resolve));
                    blobs.push(blob);
                    document.body.removeChild(wrapper);
                }

                document.body.removeChild(container);
                return blobs;
            }

            function showShareButton(blobs, groupName) {
                const dark = isDarkMode();
                const existing = document.getElementById('attendance-share-container');
                if (existing) existing.remove();

                const shareContainer = document.createElement('div');
                shareContainer.id = 'attendance-share-container';
                shareContainer.style.cssText = `
                    position:fixed; bottom:20px; right:20px; z-index:9999;
                    background:${dark ? '#1f2937' : 'white'};
                    padding:15px; border-radius:8px;
                    box-shadow:0 2px 10px rgba(0,0,0,${dark ? '0.5' : '0.15'});
                    display:flex; align-items:center; gap:10px;
                    direction:rtl; font-family:Almarai,sans-serif;
                `;

                const label = blobs.length > 1
                    ? `مشاركة التقرير (${blobs.length} صور)`
                    : 'مشاركة التقرير';

                const shareBtn = document.createElement('button');
                shareBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                         stroke-width="1.5" stroke="currentColor" style="width:18px;height:18px">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283
                               1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566
                               5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0
                               0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0
                               0 0-3.933 2.185Z" />
                    </svg>
                    ${label}
                `;
                shareBtn.style.cssText = `
                    background:${dark ? '#4f46e5' : '#3B82F6'};
                    color:white; border:none; padding:8px 16px; border-radius:6px;
                    cursor:pointer; display:flex; align-items:center; gap:8px;
                    font-size:14px; font-family:Almarai,sans-serif;
                `;

                const closeBtn = document.createElement('button');
                closeBtn.innerHTML = '&times;';
                closeBtn.style.cssText = `
                    background:${dark ? '#374151' : '#f3f4f6'};
                    border:none; border-radius:50%; width:26px; height:26px;
                    cursor:pointer; display:flex; align-items:center;
                    justify-content:center; font-size:16px;
                    color:${dark ? '#f9fafb' : '#1f2937'};
                `;

                shareBtn.onclick = async () => {
                    const files = blobs.map((blob, i) =>
                        new File([blob], `تقرير-حضور-${groupName}-صفحة-${i + 1}.png`, { type: 'image/png' })
                    );
                    if (navigator.share && navigator.canShare({ files })) {
                        try { await navigator.share({ files, title: `تقرير حضور ${groupName}` }); }
                        catch (e) { console.log('Share error:', e); }
                    }
                };

                closeBtn.onclick = () => shareContainer.remove();

                shareContainer.appendChild(shareBtn);
                shareContainer.appendChild(closeBtn);
                document.body.appendChild(shareContainer);

                setTimeout(() => {
                    if (document.body.contains(shareContainer)) shareContainer.remove();
                }, 60000);
            }

            Livewire.on('export-table', async (data) => {
                const groupData = data[0];
                const blobs     = await generateImages(groupData);
                showShareButton(blobs, groupData.groupName);
            });
        });
    </script>
@endPushOnce
