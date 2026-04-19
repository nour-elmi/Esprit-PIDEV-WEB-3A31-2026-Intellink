const window = {}; const document = { getElementById:()=>({ style:{}, classList:{add:()=>{}, remove:()=>{}} }), querySelectorAll:()=>[], querySelector:()=>({}), createElement:()=>({ style:{}, appendChild:()=>{} }) };

            // ðŸ”´ VARIABLES GLOBALES POUR LE CHAT
            let activeConversationId = null;
            let currentUserId = 1;
            let pusher = null;
            let activeChannel = null;
            let typingTimer;
            let isSending = false;
            const TYPING_TIMEOUT = 2000;
            let globalUnread = 0;

            // Fonction pour animer le badge +99
            function updateBadge() {
    const badge = document.getElementById('notifBadge');
    if (!badge) return;
    
    // Logique mixte : Messages non lus + Reclamations non lues
    const unreadRecsEl = document.getElementById('totalNotifCount');
    const recsCount = unreadRecsEl ? parseInt(unreadRecsEl.innerText || 0) : 0;
    const msgNotifItem = document.getElementById('messagerieNotifItem');
    const txtGlobalUnread = document.getElementById('txtGlobalUnread');
    
    if (globalUnread > 0 && msgNotifItem && txtGlobalUnread) {
        msgNotifItem.classList.remove('hidden');
        txtGlobalUnread.innerText = globalUnread;
    } else if (msgNotifItem) {
        msgNotifItem.classList.add('hidden');
    }
    
    const total = globalUnread + recsCount;
    
    if (total <= 0) {
        badge.classList.add('hidden');
    } else {
        badge.classList.remove('hidden');
        badge.innerText = total > 99 ? '+99' : total;
                }
            }

            // RÃ©cupÃ©rer le total au chargement de la page
            setTimeout(() => {
                const initSpan = document.getElementById('initTotalUnread');
                if (initSpan) { globalUnread = parseInt(initSpan.innerText) || 0; updateBadge(); }
            }, 500);

            // Initialisation de Pusher
            if (currentUserId) {
                pusher = new Pusher('185d0f65ec9745830a72', {
                    cluster: 'eu'
                });

                // ðŸ”´ LE CANAL GLOBAL DES NOTIFICATIONS (Pour le Badge Navbar)
                const globalChannel = pusher.subscribe('user-channel-' + currentUserId);
                globalChannel.bind('notification', function(data) {
                    const isChatPanelOpen = document.getElementById('chatWidgetPanel').classList.contains('is-active');
                    const isViewingThisChat = (activeConversationId === data.conversation_id) && !document.getElementById('viewDiscussion').classList.contains('view-hidden-right');

                    // Si on n'est pas DÉJÀ en train de lire cette conversation
                    if (!(isChatPanelOpen && isViewingThisChat)) {
                        globalUnread++;
                        updateBadge();
                        const convItem = document.getElementById('conv-item-' + data.conversation_id);
                        if (convItem) {
                            convItem.classList.add('unread-bold');
                            let cur = parseInt(convItem.getAttribute('data-unread')) || 0;
                            convItem.setAttribute('data-unread', cur + 1);
                        }
                    }
                });

                globalChannel.bind('unfriend-event', function(data) {
                    const removedUserId = (data.removerId == currentUserId) ? data.removedId : data.removerId;
                    
                    // Remove from Contact List
                    const convItem = document.getElementById('conv-item-' + data.conversationId);
                    if (convItem) convItem.remove();

                    // Search by data-user-id fallback for Conv Item and Suggestions
                    const allConvItems = document.querySelectorAll('.imessage-contact[data-user-id="'+removedUserId+'"]');
                    allConvItems.forEach(item => item.remove());

                    const allSuggestionItems = document.querySelectorAll('.suggestion-item[data-user-id="'+removedUserId+'"]');
                    allSuggestionItems.forEach(item => item.remove());

                    // If currently viewing this chat, close it
                    if (activeConversationId == data.conversationId) {
                        const viewMain = document.getElementById('viewMain');
                        const viewDiscussion = document.getElementById('viewDiscussion');
                        if (viewMain && viewDiscussion) {
                            viewMain.classList.remove('view-hidden-left');
                            viewDiscussion.classList.add('view-hidden-right');
                        }
                        activeConversationId = null;
                        
                        // Close widget panel entirely or let it stay in list
                    }
                });
            }

            // LA TECHNIQUE DE DÃ‰LÃ‰GATION (Navigation dans le widget)
            document.addEventListener('click', function(e) {
                const chatPanel = document.getElementById('chatWidgetPanel');
                if (!chatPanel) return; 

                // Les boutons d'ouverture (navbar et IA) ainsi que retour/fermer 
                // sont maintenant gÃ©rÃ©s nativement par leurs attributs onclick="...".
                // Cela Ã©vite les doublons d'ouverture/fermeture !

                const sendBtn = e.target.closest('#sendBtn');
                if (sendBtn) { envoyerMessage(); return; }

                const contactItem = e.target.closest('.imessage-contact');
                if (contactItem) {
                    const convId = contactItem.getAttribute('data-conversation-id');
                    if (convId) {
                        const name = contactItem.getAttribute('data-name');
                        const avatar = contactItem.getAttribute('data-avatar');
                        window.slideIntoDiscussion(name, avatar, convId);
                    }
                    return;
                }
            });

            // ðŸ”´ 1. OUVRIR UNE DISCUSSION (Avec historique, Pusher, et IA)
            window.slideIntoDiscussion = function(name, avatarUrl, conversationId) {
                activeConversationId = conversationId;

                let lastActivityStr = '';
                let statusColor = '';

                // Enlever le gras et mettre Ã  jour le badge quand on ouvre !
                const convItem = document.getElementById('conv-item-' + conversationId);
                if (convItem) {
                    lastActivityStr = convItem.getAttribute('data-status') || '';
                    statusColor = convItem.getAttribute('data-status-color') || '';

                    let unread = parseInt(convItem.getAttribute('data-unread')) || 0;
                    if (unread > 0) {
                        globalUnread = Math.max(0, globalUnread - unread);
                        convItem.setAttribute('data-unread', '0');
                        convItem.classList.remove('unread-bold');
                        updateBadge();
                        fetch('/api/message/read/' + conversationId, { method: 'POST' });
                    }
                }

                document.getElementById('activeName').innerText = name;
                
                const activeStatusStrSpan = document.getElementById('activeStatusStr');
                if (activeStatusStrSpan) {
                    if (avatarUrl === 'ai-logo') {
                        activeStatusStrSpan.innerText = 'Toujours en ligne';
                        activeStatusStrSpan.style.color = '#10B981';
                    } else if (lastActivityStr) {
                        activeStatusStrSpan.innerText = lastActivityStr;
                        activeStatusStrSpan.style.color = statusColor;
                    } else {
                        activeStatusStrSpan.innerText = '';
                    }
                }
                
                // GESTION DU LOGO (Avatar normal ou Animation SVG pour l'IA)
                const activeAvatarImg = document.getElementById('activeAvatar');
                const activeAvatarAi = document.getElementById('activeAvatarAi');
                
                if (avatarUrl === 'ai-logo') {
                    activeAvatarImg.style.display = 'none';
                    activeAvatarAi.style.display = 'flex';
                } else {
                    activeAvatarImg.style.display = 'block';
                    activeAvatarAi.style.display = 'none';
                    activeAvatarImg.src = avatarUrl;
                }

                document.getElementById('viewContacts').classList.remove('view-active');
                document.getElementById('viewContacts').classList.add('view-hidden-left');
                document.getElementById('viewDiscussion').classList.remove('view-hidden-right');
                document.getElementById('viewDiscussion').classList.add('view-active');
                
                const chatBody = document.getElementById('chatBody');
                
                // ðŸ¤– SI ON OUVRE LE CHAT DE L'IA
                if (conversationId === 'ai') {
                    chatBody.innerHTML = '<div class="date-stamp">Aujourd\'hui</div>';
                    // Emoji retirÃ©
                    ajouterBulleAuChat("Bonjour ! Je suis IntelLink-AI. Comment puis-je vous aider Ã  propulser votre carriÃ¨re aujourd'hui ?", 'received');
                    return; // On s'arrÃªte lÃ  (pas d'historique ni de Pusher pour l'IA)
                }

                chatBody.innerHTML = '<div style="text-align: center; margin-top: 20px; color: #8E8E93;"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>'; 
                
                // ðŸŸ¢ FETCH DE L'HISTORIQUE (Pour les vrais utilisateurs) ðŸŸ¢
                fetch('/api/message/history/' + conversationId)
                    .then(response => response.json())
                    .then(messages => {
                        chatBody.innerHTML = ''; 

                        if (messages.error) {
                            chatBody.innerHTML = `<div class="date-stamp">${messages.error}</div>`;
                            return;
                        }

                        if (messages.length === 0) {
                            chatBody.innerHTML = '<div class="date-stamp">DÃ©but de la conversation</div>';
                        } else {
                            messages.forEach(msg => {
                                const type = (msg.expediteur_id === currentUserId) ? 'sent' : 'received';
                                ajouterBulleAuChat(msg.contenu, type, msg.heure, msg.attachment);
                            });
                            marquerMessagesCommeLus();
                        }
                    })
                    .catch(error => {
                        console.error("Erreur d'historique:", error);
                        chatBody.innerHTML = '<div class="date-stamp" style="color:red;">Erreur de connexion</div>';
                    });
                
                // Fetch shared files
                const sharedFilesPanel = document.getElementById('sharedFilesPanel');
                const sharedFilesList = document.getElementById('sharedFilesList');

                if (conversationId !== 'ai') {
                    fetch('/api/messenger/shared-files/' + conversationId)
                        .then(r => r.json())
                        .then(data => {
                            if (data.files && data.files.length > 0) {
                                sharedFilesPanel.style.display = 'block';
                                sharedFilesPanel.style.padding = '5px 15px'; // Adjust padding for messenger style
                                
                                let htmlContent = `<div onclick="var c=document.getElementById('sharedFilesContainer'); var v=document.getElementById('sharedFilesChevron'); if(c.style.display==='none'){c.style.display='flex';v.style.transform='rotate(180deg)';}else{c.style.display='none';v.style.transform='rotate(0deg)';}" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; padding: 8px 0; transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.02)'" onmouseout="this.style.background='none'">
                                    <strong style="color:#050505;font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px;">
                                        Contenu multimédia et fichiers
                                    </strong> 
                                    <span style="font-size:13px;color:#65676B;display:flex;align-items:center;gap:8px;">
                                        ${data.files.length} fichiers <i class="fas fa-chevron-down" id="sharedFilesChevron" style="transition: transform 0.3s; transform: rotate(0deg);"></i>
                                    </span>
                                </div>
                                <div id="sharedFilesContainer" style="display:none; gap:10px; overflow-x:auto; padding:10px 0; border-top: 1px solid #E4E6EB;">`;
                                
                                data.files.forEach(f => {
                                    // Assurer le chemin correct pour les fichiers partagés (erreur 'Not Found')
                                    let fUrl = f.url;
                                    if (!fUrl.startsWith('/') && !fUrl.startsWith('http')) { fUrl = '/uploads/messenger/' + fUrl; }
                                    const isImg = fUrl.match(/\.(jpeg|jpg|gif|png|webp)$/i) != null;
                                    
                                    if (isImg) {
                                        htmlContent += `<a href="${fUrl}" target="_blank" style="flex-shrink:0; border-radius:10px; overflow:hidden; border:1px solid #E4E6EB; display:block; width: 60px; height: 60px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'"><img src="${fUrl}" style="width:100%; height:100%; object-fit:cover; display:block;" /></a>`;
                                    } else {
                                        htmlContent += `<a href="${fUrl}" target="_blank" style="flex-shrink:0; background:#E4E6EB; padding:8px 12px; border-radius:10px; text-decoration:none; color:#050505; font-weight:500; display:inline-flex; flex-direction: column; align-items:center; gap:6px; transition:background 0.2s; width: 60px; height: 60px; justify-content:center;" onmouseover="this.style.background='#D8DADF'" onmouseout="this.style.background='#E4E6EB'"><i class="fas fa-file-alt" style="font-size:20px; color:#555;"></i> <span style="font-size:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; width: 100%; text-align: center;">${f.fileName}</span></a>`;
                                    }
                                });
                                htmlContent += `</div>`;
                                
                                sharedFilesList.innerHTML = htmlContent;
                            } else {
                                sharedFilesPanel.style.display = 'none';
                            }
                        }).catch(() => sharedFilesPanel.style.display = 'none');
                        
                    // Show unfriend button
                    document.getElementById('unfriendBtn').style.display = 'block';
                } else {
                    sharedFilesPanel.style.display = 'none';
                    document.getElementById('unfriendBtn').style.display = 'none';
                }
                
                // Connexion au temps rÃ©el Pusher
                if (pusher && conversationId) {
                    if (activeChannel) { pusher.unsubscribe(activeChannel.name); }
                    activeChannel = pusher.subscribe('chat-conversation-' + conversationId);
                    
                    activeChannel.bind('nouveau-message', function(data) {
                        if (data.expediteur_id !== currentUserId) {
                            removeTypingIndicator(); 
                            ajouterBulleAuChat(data.contenu, 'received', data.heure, data.attachment);
                            marquerMessagesCommeLus();
                            fetch('/api/message/read/' + conversationId, { method: 'POST' });
                        }
                    });

                    activeChannel.bind('user-typing', function(data) {
                        if (data.user_id !== currentUserId) {
                            showTypingIndicator();
                        }
                    });
                }
            };

            // ðŸ”´ 2. Ã‰COUTER LE CLAVIER (Pour lancer les "...")
            document.addEventListener('input', function(e) {
                if (e.target.id === 'chatInput') {
                    if (!activeConversationId) return;
                    
                    // On n'envoie pas de "Typing" Ã  Pusher si on parle Ã  l'IA
                    if (activeConversationId !== 'ai') {
                        fetch('/api/message/typing/' + activeConversationId, { method: 'POST' });
                    }
                    
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(() => {}, TYPING_TIMEOUT);
                }
            });

            document.addEventListener('keypress', function(e) {
                if (e.target.id === 'chatInput' && e.key === 'Enter') { 
                    e.preventDefault(); 
                    envoyerMessage(); 
                }
            });

            window.handleChatFileSelect = function(input) {
                if (input.files && input.files[0]) {
                    window.selectedChatFile = input.files[0];
                    document.getElementById('fileNameDisplay').innerText = window.selectedChatFile.name;
                    document.getElementById('filePreview').style.display = 'flex';
                }
            }

            window.clearFileSelection = function() {
                window.selectedChatFile = null;
                document.getElementById('chatFileInput').value = '';
                document.getElementById('filePreview').style.display = 'none';
            }

            window.unfriendActiveUser = function() {
                if (!activeConversationId || activeConversationId === 'ai') return;
                if (confirm('Voulez-vous vraiment retirer cette personne de vos amis ?')) {
                    fetch('/api/messenger/unfriend/' + activeConversationId, { method: 'POST' })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                window.location.reload();
                            } else {
                                alert(data.message || 'Erreur serveur');
                            }
                        });
                }
            }

            // ðŸ”´ 3. ENVOYER UN MESSAGE (Humain ou IA)
            function envoyerMessage() {
                if (!activeConversationId) return;

                const chatInput = document.getElementById('chatInput');
                const texte = chatInput.value.trim();
                if (texte === '' && !window.selectedChatFile) return;
                
                const heureActuelle = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                ajouterBulleAuChat(texte, 'sent', heureActuelle, window.selectedChatFile ? '/uploads/messenger/' + window.selectedChatFile.name : null);
                chatInput.value = ''; 
                
                // ðŸ¤– SI ON PARLE Ã€ L'IA
                if (activeConversationId === 'ai') {
                    showTypingIndicator(); 
                    
                    fetch('/api/ai/chat', {
                        method: 'POST',
                        body: (function(){ const fd = new FormData(); fd.append('contenu', texte); if(window.selectedChatFile) fd.append('attachment', window.selectedChatFile); return fd; })()
                    })
                    .then(res => res.json())
                    .then(data => {
                        removeTypingIndicator(); 
                        const heureIA = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                        // Emojis retirÃ©s potentiellement dans la rÃ©ponse via backend, sinon on affiche la data.
                        ajouterBulleAuChat(data.reponse, 'received', heureIA);
                    })
                    .catch(err => {
                        removeTypingIndicator();
                        // Emoji retirÃ©
                        ajouterBulleAuChat("Erreur technique. Mes circuits sont actuellement occupÃ©s.", 'received');
                    });
                    
                    return;
                }

                // ðŸ‘¨â€ðŸ’» SI ON PARLE Ã€ UN HUMAIN (Pusher)
                fetch('/api/message/send/' + activeConversationId, {
                    method: 'POST',
                    body: (function(){ const fd = new FormData(); fd.append('contenu', texte); if(window.selectedChatFile) fd.append('attachment', window.selectedChatFile); return fd; })()
                })
                .then(() => {
                    if (window.selectedChatFile) { clearFileSelection(); }
                })
                .catch(error => console.error("Erreur d'envoi:", error));
            }

            // ðŸ”´ 4. GÃ‰NÃ‰RATEUR DE BULLES INTELLIGENT
            function ajouterBulleAuChat(texte, type, heure = null, attachmentHtml = null) {
                const chatBody = document.getElementById('chatBody');
                const wrapper = document.createElement('div'); 
                wrapper.className = 'message-wrapper ' + type;
                
                const bubble = document.createElement('div'); 
                bubble.className = 'bubble'; 
                bubble.innerText = texte;
                wrapper.appendChild(bubble); 
                
                if (attachmentHtml) {
                    let fileUrl = attachmentHtml;
                    if (!fileUrl.startsWith('/') && !fileUrl.startsWith('http')) {
                        fileUrl = '/uploads/messenger/' + fileUrl;
                    }
                    
                    const attachDiv = document.createElement('div');
                    attachDiv.style.marginTop = '8px';
                    
                    if (type === 'received') {
                        attachDiv.innerHTML = `<a href="${fileUrl}" target="_blank" style="color: var(--intel-blue); background: #ffffff; border: 1px solid rgba(0, 122, 255, 0.15); padding: 8px 12px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; text-decoration: none; font-weight: 600; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.2s, background 0.2s;" onmouseover="this.style.background='#f0f4ff'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#ffffff'; this.style.transform='translateY(0)'"><i class="fas fa-file-download" style="font-size: 1.1rem;"></i> <span>Ouvrir la pièce jointe</span></a>`;
                    } else {
                        attachDiv.innerHTML = `<a href="${fileUrl}" target="_blank" style="color: #ffffff; background: rgba(255, 255, 255, 0.2); padding: 8px 12px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; text-decoration: none; font-weight: 600; transition: background 0.2s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'"><i class="fas fa-file-download" style="font-size: 1.1rem;"></i> <span>Ouvrir</span></a>`;
                    }
                    
                    bubble.appendChild(attachDiv);
                }

                if (type === 'sent') {
                    const info = document.createElement('div');
                    info.className = 'msg-info';
                    info.innerHTML = `${heure || 'Ã€ l\'instant'} <span class="status-text">Distribué</span>`;
                    wrapper.appendChild(info);
                } else if (type === 'received' && heure) {
                    const info = document.createElement('div');
                    info.className = 'msg-info';
                    info.style.justifyContent = 'flex-start';
                    info.innerHTML = heure;
                    wrapper.appendChild(info);
                }

                chatBody.appendChild(wrapper);
                chatBody.scrollTop = chatBody.scrollHeight;
                
                return wrapper;
            }

            // ðŸ”´ 5. GESTION DE L'ANIMATION DES 3 POINTS
            function showTypingIndicator() {
                const chatBody = document.getElementById('chatBody');
                if (document.getElementById('typing-anim')) return; 

                const typingDiv = document.createElement('div');
                typingDiv.id = 'typing-anim';
                typingDiv.className = 'typing-indicator';
                typingDiv.innerHTML = '<div class="dot"></div><div class="dot"></div><div class="dot"></div>';
                
                chatBody.appendChild(typingDiv);
                chatBody.scrollTop = chatBody.scrollHeight;

                clearTimeout(window.hideTypingTimer);
                window.hideTypingTimer = setTimeout(removeTypingIndicator, 2500);
            }

            function removeTypingIndicator() {
                const typingDiv = document.getElementById('typing-anim');
                if (typingDiv) {
                    typingDiv.style.opacity = '0';
                    setTimeout(() => typingDiv.remove(), 300);
                }
            }

            // ðŸ”´ 6. SIMULATION DU "VU"
            function marquerMessagesCommeLus() {
                const statusTexts = document.querySelectorAll('.status-text');
                statusTexts.forEach(span => {
                    span.innerText = 'Lu';
                    span.style.color = '#34D399'; 
                });
            }

            // ðŸ”´ 7. GESTION DES DEMANDES D'AMIS
            window.envoyerDemande = function(userId, el) {
                const badge = el.querySelector('.suggestion-add-badge');
                const icon = badge.querySelector('i');

                badge.style.background = "#ccc";
                badge.style.borderColor = "#ccc";
                icon.className = "fas fa-spinner fa-spin";

                fetch('/api/friend-request/send/' + userId, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        badge.style.background = "var(--intel-blue)";
                        badge.style.borderColor = "white";
                        icon.className = "fas fa-plus";
                    } else {
                        badge.style.background = "#10b981";
                        badge.style.borderColor = "white";
                        icon.className = "fas fa-check";
                    }
                })
                .catch(error => {
                    console.error("Erreur:", error);
                    badge.style.background = "#ef4444";
                    icon.className = "fas fa-times";
                });
            };

            window.accepterDemande = function(requestId, btnElement) {
                const originalText = btnElement.innerText;
                btnElement.innerText = "...";
                btnElement.style.background = "#ccc";

                fetch('/api/friend-request/accept/' + requestId, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        btnElement.innerText = originalText;
                        btnElement.style.background = "var(--intel-blue)";
                    } else {
                        const demandeDiv = document.getElementById('demande-' + requestId);
                        if(demandeDiv) {
                            demandeDiv.style.transition = "opacity 0.4s ease";
                            demandeDiv.style.opacity = '0';
                            setTimeout(() => {
                                demandeDiv.remove();
                            }, 400);
                        }
                    }
                })
                .catch(error => {
                    console.error("Erreur:", error);
                    btnElement.innerText = "Erreur";
                    btnElement.style.background = "#ef4444";
                });
            };

            // =========================================
            // ðŸ”´ 8. RECHERCHE INSTANTANÃ‰E (Style iMessage)
            // =========================================
            const chatSearchInput = document.getElementById('chatSearchInput');
            if (chatSearchInput) {
                chatSearchInput.addEventListener('input', function(e) {
                    const query = e.target.value.toLowerCase().trim();

                    const contacts = document.querySelectorAll('.imessage-contact-list .imessage-contact');
                    contacts.forEach(contact => {
                        const nameElem = contact.querySelector('.name');
                        if(nameElem) {
                            const name = nameElem.innerText.toLowerCase();
                            contact.style.display = name.includes(query) ? 'flex' : 'none';
                        }
                    });

                    const suggestions = document.querySelectorAll('.imessage-suggestions-container .suggestion-item');
                    suggestions.forEach(suggestion => {
                        const nameElem = suggestion.querySelector('.suggestion-name');
                        if(nameElem) {
                            const name = nameElem.innerText.toLowerCase();
                            suggestion.style.display = name.includes(query) ? 'flex' : 'none';
                        }
                    });

                    const sectionTitles = document.querySelectorAll('.section-title');
                    sectionTitles.forEach(title => {
                        title.style.display = query.length > 0 ? 'none' : 'block';
                    });
                });
            }
            // =========================================
            //  9. POLICE D'ETAT TEMPS-REEL (AJAX)
            // =========================================
            setInterval(() => {
                fetch('/api/notifications/poll')
                    .then(r => {
                        if (r.ok) return r.json();
                        throw new Error('Non authentifiÃ©');
                    })
                    .then(data => {
                        if (data.totalNotifs !== undefined) {
                            const badge = document.getElementById('notifBadge');
                            const totalBadge = document.getElementById('totalNotifCount');
                            
                            if (badge && totalBadge) {
                                // Add messenger count to the backend total (chat sets globalUnread via its own script)
                                const globalUnreadSpan = document.getElementById('txtGlobalUnread');
                                const messengerCount = globalUnreadSpan ? parseInt(globalUnreadSpan.innerText) || 0 : 0;
                                const grandTotal = data.totalNotifs + messengerCount;

                                totalBadge.innerText = grandTotal;
                                badge.innerText = grandTotal;

                                if (grandTotal > 0) {
                                    badge.classList.remove('hidden');
                                } else {
                                    badge.classList.add('hidden');
                                }
                            }
                        }
                    })
                    .catch(err => console.log('Poll inactif:', err));
            }, 10000); // Check every 10 seconds

        