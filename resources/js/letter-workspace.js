import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import '../css/letter-workspace.css';

GlobalWorkerOptions.workerSrc = workerUrl;

const workspace = document.querySelector('[data-letter-form], [data-letter-document]');
if (workspace) {
    const form = workspace.matches('form') ? workspace : null;
    const pages = workspace.querySelector('[data-letter-pages]');
    const previewStatus = workspace.querySelector('[data-preview-status]');
    const saveStatus = workspace.querySelector('[data-save-status]');
    const pageInput = workspace.querySelector('[data-page-input]');
    const dialog = document.querySelector('[data-letter-email-dialog]');
    const emailForm = dialog?.querySelector('form');
    const csrf = () => window.KfmsSession?.token()
        || document.querySelector('meta[name="csrf-token"]').content;
    const refreshCsrf = async () => {
        await window.KfmsSession?.refresh?.();
        return csrf();
    };
    let revision = 0;
    let renderedRevision = -1;
    let dirty = false;
    let timer;
    let request;
    let loading;
    let pdf;
    let pdfBlob;
    let currentPage = 1;
    let observer;
    let saving = false;
    let emailing = false;
    let exporting = false;

    const showError = (element, message = '') => {
        if (!element) return;
        element.textContent = message;
        element.hidden = !message;
    };

    const checkedResponse = async (response) => {
        if (response.ok && !response.redirected) return response;
        const data = await response.json().catch(() => ({}));
        const validation = Object.values(data.errors || {}).flat().join(' ');
        throw new Error(validation || (response.status === 419 || response.redirected
            ? 'Your session has expired. Sign in again before saving or sending.'
            : data.message || 'The request failed. Please try again.'));
    };

    const filename = () => {
        const reference = form?.elements.reference_no.value || workspace.dataset.reference || 'letter';
        return reference.replace(/[^a-zA-Z0-9_-]/g, '-').toLowerCase() + '.pdf';
    };

    const updateNavigation = () => {
        const total = pdf?.numPages || 1;
        pageInput.value = currentPage;
        pageInput.max = total;
        pageInput.disabled = !pdf;
        workspace.querySelector('[data-page-total]').textContent = 'of ' + total;
        workspace.querySelector('[data-page-prev]').disabled = !pdf || currentPage <= 1;
        workspace.querySelector('[data-page-next]').disabled = !pdf || currentPage >= total;
    };

    const goToPage = (number) => {
        if (!pdf) return;
        currentPage = Math.max(1, Math.min(pdf.numPages, Number(number) || 1));
        const target = pages.children[currentPage - 1];
        pages.scrollTo({ top: target.offsetTop - 16, behavior: 'smooth' });
        updateNavigation();
    };

    const renderPage = async (document, number, canvas, scale = 1.6) => {
        const page = await document.getPage(number);
        const viewport = page.getViewport({ scale });
        canvas.width = Math.ceil(viewport.width);
        canvas.height = Math.ceil(viewport.height);
        await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        const text = await page.getTextContent();
        canvas.textContent = text.items.map((item) => item.str).join(' ');
    };

    const displayDocument = async (document, version) => {
        observer?.disconnect();
        const fragment = window.document.createDocumentFragment();
        for (let number = 1; number <= document.numPages; number++) {
            const sheet = window.document.createElement('figure');
            sheet.className = 'kfms-letter-page';
            sheet.dataset.page = number;
            const caption = window.document.createElement('figcaption');
            caption.textContent = 'Page ' + number + ' of ' + document.numPages;
            const canvas = window.document.createElement('canvas');
            canvas.setAttribute('aria-label', 'Letter page ' + number);
            canvas.setAttribute('role', 'img');
            sheet.append(caption, canvas);
            fragment.append(sheet);
        }
        const first = fragment.querySelector('canvas');
        await renderPage(document, 1, first);
        first.dataset.rendered = 'true';
        if (version !== revision) return;
        const oldScroll = pages.scrollTop;
        pages.replaceChildren(fragment);
        pages.scrollTop = oldScroll;
        observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const canvas = entry.target.querySelector('canvas');
                if (canvas.dataset.rendered) return;
                canvas.dataset.rendered = 'true';
                renderPage(document, Number(entry.target.dataset.page), canvas).catch(() => {
                    delete canvas.dataset.rendered;
                    if (document === pdf) previewStatus.textContent = 'Page could not load. Refresh the preview to retry.';
                });
            });
        }, { root: pages, rootMargin: '600px' });
        pages.querySelectorAll('figure').forEach((sheet) => observer.observe(sheet));
    };

    const refreshPreview = async () => {
        clearTimeout(timer);
        const version = revision;
        if (pdf && renderedRevision === version) return;
        if (loading?.version === version) return loading.promise;
        request?.abort();
        request = new AbortController();
        const signal = request.signal;
        previewStatus.textContent = 'Updating preview...';
        previewStatus.classList.remove('is-error');
        pages.setAttribute('aria-busy', 'true');
        const promise = (async () => {
            let next;
            try {
                const previewToken = form ? await refreshCsrf() : csrf();
                const data = form ? new FormData(form) : null;
                data?.delete('_method');
                data?.delete('documents[]');
                const response = await checkedResponse(await fetch(workspace.dataset.previewUrl, {
                    method: form ? 'POST' : 'GET',
                    headers: { Accept: 'application/pdf, application/json', 'X-CSRF-TOKEN': previewToken },
                    body: data,
                    signal,
                }));
                if (!(response.headers.get('Content-Type') || '').includes('application/pdf')) {
                    throw new Error('The preview could not load. Please refresh and try again.');
                }
                const blob = await response.blob();
                next = await getDocument({ data: await blob.arrayBuffer(), isEvalSupported: false }).promise;
                if (version !== revision || signal.aborted) { await next.loadingTask.destroy(); return; }
                await displayDocument(next, version);
                if (version !== revision || signal.aborted) { await next.loadingTask.destroy(); return; }
                const previous = pdf;
                pdf = next;
                pdfBlob = blob;
                renderedRevision = version;
                currentPage = Math.min(currentPage, pdf.numPages);
                updateNavigation();
                const summary = pdf.numPages === 1 ? '1 A4 page' : pdf.numPages + ' A4 pages';
                previewStatus.textContent = pdf.numPages === 1 ? summary : summary + ' - continues on the next page';
                workspace.querySelectorAll('[data-page-summary]').forEach((element) => {
                    element.textContent = summary;
                    element.classList.toggle('is-multipage', pdf.numPages > 1);
                });
                pages.setAttribute('aria-busy', 'false');
                await previous?.loadingTask.destroy();
            } catch (error) {
                if (version !== revision || signal.aborted) return;
                previewStatus.textContent = error.message;
                previewStatus.classList.add('is-error');
                pages.setAttribute('aria-busy', 'false');
                throw error;
            } finally {
                if (loading?.version === version) loading = null;
            }
        })();
        loading = { version, promise };
        return promise;
    };

    const markChanged = () => {
        dirty = true;
        revision++;
        saveStatus.textContent = 'Unsaved changes';
        previewStatus.textContent = 'Updating preview...';
        workspace.querySelectorAll('[data-page-summary]').forEach((element) => { element.textContent = 'Updating pages...'; });
        clearTimeout(timer);
        timer = setTimeout(() => refreshPreview().catch(() => {}), 700);
    };

    const latestPdf = async () => {
        await refreshPreview();
        if (renderedRevision !== revision) await latestPdf();
        return pdf;
    };

    workspace.querySelector('[data-page-prev]').addEventListener('click', () => goToPage(currentPage - 1));
    workspace.querySelector('[data-page-next]').addEventListener('click', () => goToPage(currentPage + 1));
    pageInput.addEventListener('change', () => goToPage(pageInput.value));
    pageInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') { event.preventDefault(); goToPage(pageInput.value); }
    });
    workspace.querySelector('[data-preview-retry]').addEventListener('click', () => {
        renderedRevision = -1;
        refreshPreview().catch(() => {});
    });
    workspace.querySelector('[data-jump-last]')?.addEventListener('click', async () => {
        try {
            await latestPdf();
            goToPage(pdf.numPages);
            if (matchMedia('(max-width: 1199px)').matches) pages.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (_) { /* The preview status contains the error. */ }
    });
    pages.addEventListener('scroll', () => {
        const bounds = pages.getBoundingClientRect();
        const visible = [...pages.children].find((sheet) => sheet.getBoundingClientRect().bottom > bounds.top + 80);
        if (visible?.dataset.page) {
            currentPage = Number(visible.dataset.page);
            updateNavigation();
        }
    }, { passive: true });

    workspace.querySelectorAll('[data-letter-download]').forEach((button) => button.addEventListener('click', async () => {
        if (exporting) return;
        exporting = true;
        button.disabled = true;
        try {
            await latestPdf();
            const url = URL.createObjectURL(pdfBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename();
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 60000);
        } catch (error) { showError(workspace.querySelector('[data-letter-error]'), error.message); }
        finally { exporting = false; button.disabled = false; }
    }));

    workspace.querySelectorAll('[data-letter-print]').forEach((button) => button.addEventListener('click', async () => {
        if (exporting) return;
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            showError(workspace.querySelector('[data-letter-error]'), 'Allow pop-ups to print this letter, or download the PDF.');
            return;
        }
        printWindow.document.title = 'Print letter';
        printWindow.document.body.textContent = 'Preparing letter...';
        exporting = true;
        button.disabled = true;
        workspace.inert = true;
        try {
            const documentPdf = await latestPdf();
            const style = printWindow.document.createElement('style');
            style.textContent = '@page{size:A4 portrait;margin:0}*{box-sizing:border-box}body{margin:0;background:#eee}img{display:block;width:210mm;height:297mm;break-after:page;margin:0 auto}img:last-child{break-after:auto}@media print{body{background:white}}';
            printWindow.document.head.append(style);
            printWindow.document.body.replaceChildren();
            printWindow.document.title = filename();
            for (let number = 1; number <= documentPdf.numPages; number++) {
                const canvas = document.createElement('canvas');
                await renderPage(documentPdf, number, canvas, 2);
                const image = printWindow.document.createElement('img');
                image.src = canvas.toDataURL('image/png');
                image.alt = 'Letter page ' + number;
                printWindow.document.body.append(image);
                await image.decode();
            }
            printWindow.focus();
            printWindow.print();
        } catch (error) {
            printWindow.close();
            showError(workspace.querySelector('[data-letter-error]'), error.message);
        } finally { exporting = false; button.disabled = false; workspace.inert = false; }
    }));

    const saveLetter = async () => {
        if (!form || saving) return;
        saving = true;
        const version = revision;
        form.inert = true;
        saveStatus.textContent = 'Saving...';
        showError(workspace.querySelector('[data-letter-error]'));
        try {
            const saveToken = await refreshCsrf();
            const data = new FormData(form);
            const response = await checkedResponse(await fetch(form.action, {
                method: 'POST', body: data, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': saveToken },
            }));
            const saved = await response.json();
            form.action = saved.update_url;
            let method = form.querySelector('[name="_method"]');
            if (!method) {
                method = document.createElement('input');
                method.type = 'hidden'; method.name = '_method'; form.append(method);
            }
            method.value = 'PUT';
            form.dataset.previewUrl = saved.preview_url;
            form.dataset.emailUrl = saved.email_url;
            form.dataset.saved = 'true';
            if (!form.elements.reference_no.value) {
                form.elements.reference_no.value = saved.reference_no;
                revision++;
            }
            form.querySelector('[name="documents[]"]').value = '';
            form.querySelector('[name="signature_upload"]').value = '';
            form.querySelector('[name="drawn_signature"]').value = '';
            if (revision === version || revision === version + 1) dirty = false;
            saveStatus.textContent = dirty ? 'Unsaved changes' : 'Saved';
            history.replaceState(null, '', saved.edit_url);
            renderedRevision = -1;
            refreshPreview().catch(() => {});
        } catch (error) {
            saveStatus.textContent = 'Not saved';
            throw error;
        } finally { saving = false; form.inert = false; }
    };

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            try { await saveLetter(); }
            catch (error) { showError(workspace.querySelector('[data-letter-error]'), error.message); }
        });
        ['input', 'change'].forEach((name) => form.addEventListener(name, (event) => {
            if (event.target.name && !event.target.closest('[data-letter-email-form]')) markChanged();
        }));
        form.querySelector('[data-letter-template]').addEventListener('change', (event) => {
            const option = event.target.selectedOptions[0];
            if (!option?.value) return;
            form.elements.letter_type.value = option.dataset.category || form.elements.letter_type.value;
            form.elements.subject.value = option.dataset.subject || '';
            form.elements.body.value = option.dataset.body || '';
            form.elements.letterhead_id.value = option.dataset.letterhead || '';
        });
        const signatureMode = form.querySelector('[data-signature-mode]');
        const syncSignatureMode = () => {
            form.querySelector('[data-signature-upload]').style.display = signatureMode.value === 'upload' ? '' : 'none';
            form.querySelector('[data-signature-draw]').style.display = signatureMode.value === 'drawn' ? '' : 'none';
        };
        signatureMode.addEventListener('change', syncSignatureMode);
        syncSignatureMode();
        const canvas = form.querySelector('.kfms-signature-pad');
        const context = canvas.getContext('2d');
        const signature = form.querySelector('[data-drawn-signature]');
        let drawing = false;
        const point = (event) => {
            const bounds = canvas.getBoundingClientRect();
            return [(event.clientX - bounds.left) * canvas.width / bounds.width, (event.clientY - bounds.top) * canvas.height / bounds.height];
        };
        canvas.addEventListener('pointerdown', (event) => {
            drawing = true;
            canvas.setPointerCapture(event.pointerId);
            context.beginPath();
            context.moveTo(...point(event));
        });
        canvas.addEventListener('pointermove', (event) => {
            if (!drawing) return;
            context.lineWidth = 2;
            context.lineCap = 'round';
            context.strokeStyle = '#061a32';
            context.lineTo(...point(event));
            context.stroke();
        });
        const finishDrawing = () => {
            if (!drawing) return;
            drawing = false;
            signature.value = canvas.toDataURL('image/png');
            markChanged();
        };
        canvas.addEventListener('pointerup', finishDrawing);
        canvas.addEventListener('pointercancel', finishDrawing);
        form.querySelector('[data-clear-signature]').addEventListener('click', () => {
            context.clearRect(0, 0, canvas.width, canvas.height);
            signature.value = canvas.toDataURL('image/png');
            markChanged();
        });
        window.addEventListener('beforeunload', (event) => {
            if (dirty) { event.preventDefault(); event.returnValue = ''; }
        });
    }

    workspace.querySelectorAll('[data-letter-email-open]').forEach((button) => button.addEventListener('click', () => {
        if (!dialog || (form && !form.reportValidity())) return;
        if (form) {
            emailForm.elements.to.value = form.elements.recipient_email.value;
            emailForm.elements.subject.value = form.elements.subject.value;
        }
        dialog.querySelector('[data-email-attachment]').textContent = filename();
        showError(dialog.querySelector('[data-email-error]'));
        dialog.showModal();
    }));
    dialog?.querySelectorAll('[data-email-close]').forEach((button) => button.addEventListener('click', () => {
        if (!emailing) dialog.close();
    }));
    dialog?.addEventListener('cancel', (event) => { if (emailing) event.preventDefault(); });
    emailForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (emailing) return;
        emailing = true;
        const send = emailForm.querySelector('[data-email-send]');
        send.disabled = true;
        const status = emailForm.querySelector('[data-email-status]');
        status.textContent = form ? 'Saving letter...' : 'Sending...';
        showError(emailForm.querySelector('[data-email-error]'));
        try {
            if (form && (dirty || form.dataset.saved !== 'true')) await saveLetter();
            workspace.inert = true;
            status.textContent = 'Sending...';
            const emailToken = await refreshCsrf();
            const response = await checkedResponse(await fetch(workspace.dataset.emailUrl, {
                method: 'POST', body: new FormData(emailForm),
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': emailToken },
            }));
            const result = await response.json();
            saveStatus.textContent = result.message;
            dialog.close();
        } catch (error) { showError(emailForm.querySelector('[data-email-error]'), error.message); }
        finally {
            emailing = false;
            workspace.inert = false;
            send.disabled = false;
            status.textContent = '';
        }
    });

    refreshPreview().catch(() => {});
}
