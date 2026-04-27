@pushOnce('scripts')
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Reem+Kufi:wght@500;700&family=Almarai:wght@400;700;800&display=swap"
        rel="stylesheet">
    <script>
        document.addEventListener('livewire:initialized', () => {
            async function waitForFonts() {
                if (document.fonts && document.fonts.ready) {
                    try { await document.fonts.ready; } catch (_) {}
                }
                const probes = [
                    ['700 16px Amiri', 'تقرير'],
                    ['600 14px "Reem Kufi"', 'نسبة'],
                    ['700 14px Almarai', 'الحضور'],
                ];
                if (document.fonts && typeof document.fonts.load === 'function') {
                    await Promise.all(probes.map(([font, text]) =>
                        document.fonts.load(font, text).catch(() => null)
                    ));
                }
            }

            async function waitForImages(root) {
                const images = Array.from(root.querySelectorAll('img'));
                await Promise.all(images.map(img => {
                    if (img.complete && img.naturalWidth > 0) return Promise.resolve();
                    return new Promise(resolve => {
                        img.addEventListener('load', resolve, { once: true });
                        img.addEventListener('error', resolve, { once: true });
                    });
                }));
            }

            async function generateImages(groupData) {
                const container = document.createElement('div');
                container.style.cssText = 'position:absolute;left:-9999px;top:0;width:800px;';
                container.innerHTML = groupData.html;
                document.body.appendChild(container);

                await waitForFonts();
                await waitForImages(container);
                await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));

                const pages = container.querySelectorAll('.table-page');
                const blobs = [];

                for (const page of pages) {
                    const canvas = await html2canvas(page, {
                        scale: 2,
                        backgroundColor: '#ffffff',
                        useCORS: true,
                        logging: false,
                        windowWidth: 800,
                    });
                    const blob = await new Promise(resolve => canvas.toBlob(resolve));
                    blobs.push(blob);
                }

                document.body.removeChild(container);
                return blobs;
            }

            function showShareButton(blobs, groupName) {
                const existing = document.getElementById('attendance-share-container');
                if (existing) existing.remove();

                const shareContainer = document.createElement('div');
                shareContainer.id = 'attendance-share-container';
                shareContainer.style.cssText = `
                    position:fixed; bottom:20px; right:20px; z-index:9999;
                    background:white; padding:15px; border-radius:8px;
                    box-shadow:0 2px 10px rgba(0,0,0,0.15);
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
                    background:#0d5d3f; color:white; border:none; padding:8px 16px;
                    border-radius:6px; cursor:pointer; display:flex; align-items:center;
                    gap:8px; font-size:14px; font-family:Almarai,sans-serif;
                `;

                const closeBtn = document.createElement('button');
                closeBtn.innerHTML = '&times;';
                closeBtn.style.cssText = `
                    background:#f3f4f6; border:none; border-radius:50%;
                    width:26px; height:26px; cursor:pointer;
                    display:flex; align-items:center; justify-content:center;
                    font-size:16px; color:#1f2937;
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
                const blobs = await generateImages(groupData);
                showShareButton(blobs, groupData.groupName);
            });
        });
    </script>
@endPushOnce
