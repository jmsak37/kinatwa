(function() {
    function byId(id) {
        return document.getElementById(id);
    }

    function getPlayerArea() {
        return byId("playerArea");
    }

    function getErrorPopup() {
        return byId("errorPopup");
    }

    function getErrorPopupMessage() {
        return byId("errorPopupMessage");
    }

    function closeErrorPopup() {
        const popup = getErrorPopup();
        if (popup) popup.classList.add("hidden");
    }

    function showErrorPopup(message) {
        const popup = getErrorPopup();
        const msg = getErrorPopupMessage();
        if (!popup || !msg) return;
        msg.textContent = message || "Playback error.";
        popup.classList.remove("hidden");
    }

    window.closeErrorPopup = closeErrorPopup;

    let docIndex = 0;
    let itemIndex = 0;
    let mode = "content";
    let timerId = null;
    let refreshWatcherId = null;
    let lastRefreshToken = Number(window.DISPLAY_REFRESH_TOKEN || 1);

    function clearPlayerTimer() {
        if (timerId) {
            clearTimeout(timerId);
            timerId = null;
        }
    }

    function safePlaylist() {
        const raw = window.DOCUMENT_PLAYLIST;
        if (!Array.isArray(raw)) return [];
        return raw.filter(doc => doc && typeof doc === "object" && Array.isArray(doc.items) && doc.items.length > 0);
    }

    function hasForcedPlaylist() {
        const list = safePlaylist();
        return list.some(doc => !!doc.forced);
    }

    function escapeHtml(text) {
        return String(text || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

    function frameMessages(showBottom) {
        if (!showBottom) return "";
        return `
            <div class="bottom-frame-zone">
                <div class="single-bottom-message message-1">
                    <span>KINATWA SACCO IS THE BEST AND SAFEST</span>
                </div>
                <div class="single-bottom-message message-2">
                    <span>I WISH YOU A NICE JOURNEY DEAR CUSTOMER</span>
                </div>
                <div class="single-bottom-message message-3">
                    <span>WELCOME AGAIN</span>
                </div>
            </div>
        `;
    }

    function getWelcomeMessage() {
        return `
            <div class="welcome-card welcome-animate">
                <h2>Welcome to Kinatwa Sacco</h2>
                <p>Safe travel, comfort, and reliable service every day.</p>
                <p>Kinatwa Sacco values your time and your journey.</p>
                <p>We are committed to helping you board safely and arrive safely.</p>
                <p>Thank you for choosing Kinatwa Sacco.</p>
            </div>
        `;
    }

    function showNoDocumentMessage() {
        const playerArea = getPlayerArea();
        if (!playerArea) return;
        playerArea.innerHTML = `
            <div class="welcome-card">
                <h2>Welcome to Kinatwa Sacco</h2>
                <p>No document uploaded yet.</p>
                <p>Please upload files from the admin panel.</p>
            </div>
        `;
    }

    function fitClass(fitMode) {
        if (fitMode === "fit_width") return "fit-width-priority";
        if (fitMode === "fit_height") return "fit-document-pop";
        if (fitMode === "stretch_safe") return "fit-cover";
        return "fit-contain-strong";
    }

    function showTextSlide(content, showBottom) {
        const playerArea = getPlayerArea();
        if (!playerArea) return;

        playerArea.innerHTML = `
            <div class="player-slide document-popup-wrap">
                <div class="document-popup document-popup-text popup-animate">
                    ${frameMessages(showBottom)}
                    <div class="text-slide">
                        <div class="text-content">${escapeHtml(content || "No readable content found.")}</div>
                    </div>
                </div>
            </div>
        `;
    }

    function showAdminMessageSlide(content, showBottom) {
        const playerArea = getPlayerArea();
        if (!playerArea) return;

        playerArea.innerHTML = `
            <div class="player-slide document-popup-wrap">
                <div class="document-popup document-popup-text popup-animate">
                    ${frameMessages(showBottom)}
                    <div class="text-slide">
                        <div class="text-content" style="font-size: clamp(24px, 2.2vw, 50px); text-align:left;">
                            ${content || ""}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function showImageSlide(src, fitMode, showBottom) {
        const playerArea = getPlayerArea();
        if (!playerArea) return;

        const cls = fitClass(fitMode);

        playerArea.innerHTML = `
            <div class="player-slide document-popup-wrap">
                <div class="document-popup document-popup-media popup-animate">
                    ${frameMessages(showBottom)}
                    <div class="media-stage">
                        <img id="smartImage" class="${cls}" src="${src}" alt="document image">
                    </div>
                </div>
            </div>
        `;

        const img = byId("smartImage");
        if (img) {
            img.onerror = function() {
                showErrorPopup("Image failed to load.");
            };
        }
    }

    function showVideoSlide(src, fitMode, showBottom, forcedLoop, onFinished) {
        const playerArea = getPlayerArea();
        if (!playerArea) return;

        const cls = fitClass(fitMode);

        playerArea.innerHTML = `
            <div class="player-slide media-slide">
                <div class="video-frame-wrap popup-animate-soft">
                    ${frameMessages(showBottom)}
                    <div class="video-frame-inner">
                        <video id="autoVideo" class="${cls}" autoplay muted playsinline ${forcedLoop ? "loop" : "controls"}>
                            <source src="${src}">
                            Your browser does not support video.
                        </video>
                    </div>
                </div>
            </div>
        `;

        const video = byId("autoVideo");
        if (!video) {
            showErrorPopup("Video player could not open.");
            timerId = setTimeout(onFinished, 2500);
            return;
        }

        video.onerror = function() {
            showErrorPopup("Cannot play this video.");
            timerId = setTimeout(onFinished, 2500);
        };

        if (!forcedLoop) {
            video.onended = function() {
                onFinished();
            };
        }

        video.play().catch(function() {
            showErrorPopup("Autoplay failed for video.");
            timerId = setTimeout(onFinished, 3500);
        });
    }

    function showIframeSlide(src, showBottom) {
        const playerArea = getPlayerArea();
        if (!playerArea) return;

        playerArea.innerHTML = `
            <div class="player-slide document-popup-wrap">
                <div class="document-popup document-popup-media popup-animate">
                    ${frameMessages(showBottom)}
                    <div class="media-stage">
                        <iframe
                            src="${src}"
                            style="width:100%;height:100%;border:none;border-radius:24px;background:#fff;"
                            title="Document Preview">
                        </iframe>
                    </div>
                </div>
            </div>
        `;
    }

    function moveToNextDocument() {
        const playlist = safePlaylist();
        if (!playlist.length) {
            showNoDocumentMessage();
            return;
        }

        docIndex += 1;
        if (docIndex >= playlist.length) {
            docIndex = 0;
        }

        itemIndex = 0;
        mode = hasForcedPlaylist() ? "content" : "welcome";
        renderCurrent();
    }

    function moveToNextItem() {
        const playlist = safePlaylist();
        if (!playlist.length) {
            showNoDocumentMessage();
            return;
        }

        const currentDoc = playlist[docIndex];
        if (!currentDoc || !Array.isArray(currentDoc.items) || !currentDoc.items.length) {
            moveToNextDocument();
            return;
        }

        itemIndex += 1;

        if (itemIndex >= currentDoc.items.length) {
            moveToNextDocument();
            return;
        }

        renderCurrent();
    }

    function renderCurrent() {
        clearPlayerTimer();

        const playlist = safePlaylist();
        const playerArea = getPlayerArea();

        if (!playerArea) return;

        if (Array.isArray(window.PLAYBACK_ERRORS) && window.PLAYBACK_ERRORS.length > 0) {
            // block popup visibility as requested, but still log errors
            console.warn("Playback warnings:", window.PLAYBACK_ERRORS);
        }

        if (!playlist.length) {
            showNoDocumentMessage();
            return;
        }

        try {
            if (mode === "welcome" && !hasForcedPlaylist()) {
                playerArea.innerHTML = getWelcomeMessage();
                timerId = setTimeout(function() {
                    mode = "content";
                    itemIndex = 0;
                    renderCurrent();
                }, 4000);
                return;
            }

            const currentDoc = playlist[docIndex];
            if (!currentDoc || !Array.isArray(currentDoc.items) || !currentDoc.items.length) {
                moveToNextDocument();
                return;
            }

            const item = currentDoc.items[itemIndex];
            if (!item || typeof item !== "object") {
                moveToNextDocument();
                return;
            }

            const showBottom = item.show_bottom_messages !== false;
            const forcedDoc = !!currentDoc.forced;
            const itemSeconds = Math.max(1, Number(item.seconds || 6));

            if (item.type === "admin_message") {
                showAdminMessageSlide(item.html || escapeHtml(item.content || ""), false);
                timerId = setTimeout(function() {
                    if (forcedDoc) {
                        window.location.reload();
                    } else {
                        moveToNextItem();
                    }
                }, itemSeconds * 1000);
                return;
            }

            if (item.type === "image") {
                if (!item.src) {
                    showTextSlide("No readable content found.", false);
                    timerId = setTimeout(moveToNextItem, 2500);
                    return;
                }

                showImageSlide(item.src, item.fit || "fit_both", showBottom);

                if (forcedDoc) {
                    timerId = setTimeout(function() {
                        window.location.reload();
                    }, itemSeconds * 1000);
                } else {
                    timerId = setTimeout(moveToNextItem, itemSeconds * 1000);
                }
                return;
            }

            if (item.type === "video") {
                if (!item.src) {
                    showTextSlide("No readable content found.", false);
                    timerId = setTimeout(moveToNextItem, 2500);
                    return;
                }

                if (forcedDoc) {
                    showVideoSlide(item.src, item.fit || "fit_both", showBottom, true, function() {});
                    timerId = setTimeout(function() {
                        window.location.reload();
                    }, itemSeconds * 1000);
                } else {
                    showVideoSlide(item.src, item.fit || "fit_both", showBottom, false, moveToNextItem);
                    timerId = setTimeout(moveToNextItem, itemSeconds * 1000);
                }
                return;
            }

            if (item.type === "iframe") {
                if (!item.src) {
                    showTextSlide("No readable content found.", false);
                    timerId = setTimeout(moveToNextItem, 2500);
                    return;
                }

                showIframeSlide(item.src, showBottom);

                if (forcedDoc) {
                    timerId = setTimeout(function() {
                        window.location.reload();
                    }, itemSeconds * 1000);
                } else {
                    timerId = setTimeout(moveToNextItem, itemSeconds * 1000);
                }
                return;
            }

            showTextSlide(item.content || "No readable content found.", showBottom);

            if (forcedDoc) {
                timerId = setTimeout(function() {
                    window.location.reload();
                }, itemSeconds * 1000);
            } else {
                timerId = setTimeout(moveToNextItem, itemSeconds * 1000);
            }
        } catch (err) {
            console.error(err);
            showTextSlide("No readable content found.", false);
            timerId = setTimeout(moveToNextDocument, 2500);
        }
    }

    function startPlayer() {
        try {
            const playlist = safePlaylist();

            if (!playlist.length) {
                showNoDocumentMessage();
                return;
            }

            docIndex = 0;
            itemIndex = 0;
            mode = hasForcedPlaylist() ? "content" : "welcome";
            renderCurrent();
        } catch (err) {
            console.error(err);
            showNoDocumentMessage();
        }
    }

    async function watchDisplayRefresh() {
        if (!window.IS_MAIN_DISPLAY) return;

        if (refreshWatcherId) {
            clearInterval(refreshWatcherId);
        }

        refreshWatcherId = setInterval(async function() {
            try {
                const res = await fetch("/kinatwa/backend/get_playlist.php?ts=" + Date.now(), {
                    cache: "no-store"
                });
                if (!res.ok) return;

                const data = await res.json();
                const newVersion = Number(data.refresh_token || 1);

                if (newVersion !== lastRefreshToken) {
                    window.location.reload();
                }
            } catch (err) {
                console.warn("Display refresh poll failed:", err);
            }
        }, 3000);
    }

    window.onerror = function(message, source, lineno) {
        console.error("System error:", message, source, lineno);
        return true;
    };

    window.addEventListener("unhandledrejection", function(event) {
        console.error("Unhandled promise rejection:", event.reason);
    });

    window.addEventListener("resize", function() {
        try {
            renderCurrent();
        } catch (err) {
            console.error("Resize error:", err);
        }
    });

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function() {
            startPlayer();
            watchDisplayRefresh();
        });
    } else {
        startPlayer();
        watchDisplayRefresh();
    }
})();