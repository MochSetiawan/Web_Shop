document.addEventListener('DOMContentLoaded', function() {
    // Elements
    let chatToggleButton, chatContainer, chatHeader, chatBody, 
        chatSidebar, chatConversation, chatMessages, chatInput, chatInputText, chatSendButton;
    
    // State variables
    let isMinimized = true;
    let isLoading = false;
    let activeConversationId = null;
    let conversations = [];
    let messages = [];
    let currentRole = '';
    let lastPollTime = 0;
    const pollInterval = 5000; // Poll every 5 seconds
    
    // Initialize chat after DOM loaded
    function initializeChat() {
        // Create chat toggle button
        createChatToggleButton();
        
        // Load user information
        fetchUserInfo();
        
        // Poll for new messages
        setInterval(pollNewMessages, pollInterval);
    }
    
    function createChatToggleButton() {
        chatToggleButton = document.createElement('div');
        chatToggleButton.className = 'chat-toggle-button';
        chatToggleButton.innerHTML = '<i class="fas fa-comments"></i>';
        chatToggleButton.addEventListener('click', toggleChat);
        
        // Add notification badge (hidden initially)
        const notificationBadge = document.createElement('div');
        notificationBadge.className = 'chat-notifications';
        notificationBadge.style.display = 'none';
        notificationBadge.id = 'chat-notifications';
        notificationBadge.textContent = '0';
        
        chatToggleButton.appendChild(notificationBadge);
        document.body.appendChild(chatToggleButton);
    }
    
    function createChatContainer() {
        // Create main container
        chatContainer = document.createElement('div');
        chatContainer.className = 'chat-container minimized';
        
        // Create header
        chatHeader = document.createElement('div');
        chatHeader.className = 'chat-header';
        chatHeader.innerHTML = `
            <h5><i class="fas fa-comments mr-2"></i> Chat</h5>
            <div class="chat-header-buttons">
                <button id="chat-minimize"><i class="fas fa-minus"></i></button>
                <button id="chat-close"><i class="fas fa-times"></i></button>
            </div>
        `;
        
        // Create body
        chatBody = document.createElement('div');
        chatBody.className = 'chat-body';
        
        // Add sidebar (conversations list) to body
        chatSidebar = document.createElement('div');
        chatSidebar.className = 'chat-sidebar';
        chatBody.appendChild(chatSidebar);
        
        // Add conversation area to body (initially hidden)
        chatConversation = document.createElement('div');
        chatConversation.className = 'chat-conversation';
        chatConversation.style.display = 'none';
        
        // Add messages area to conversation
        chatMessages = document.createElement('div');
        chatMessages.className = 'chat-messages';
        chatConversation.appendChild(chatMessages);
        
        // Add input area to conversation
        chatInput = document.createElement('div');
        chatInput.className = 'chat-input';
        chatInput.innerHTML = `
            <input type="text" placeholder="Tulis pesan..." id="chat-input-text">
            <button id="chat-send-button"><i class="fas fa-paper-plane"></i></button>
        `;
        chatConversation.appendChild(chatInput);
        
        // Add conversation to body
        chatBody.appendChild(chatConversation);
        
        // Combine elements
        chatContainer.appendChild(chatHeader);
        chatContainer.appendChild(chatBody);
        
        // Add to document
        document.body.appendChild(chatContainer);
        
        // Get references to elements
        chatInputText = document.getElementById('chat-input-text');
        chatSendButton = document.getElementById('chat-send-button');
        
        // Add event listeners
        chatHeader.addEventListener('click', function(e) {
            if (e.target.closest('#chat-minimize') || e.target.closest('#chat-close')) {
                return;
            }
            toggleMinimize();
        });
        
        document.getElementById('chat-minimize').addEventListener('click', toggleMinimize);
        document.getElementById('chat-close').addEventListener('click', closeChat);
        
        chatSendButton.addEventListener('click', sendMessage);
        chatInputText.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    }
    
    function toggleChat() {
        if (!chatContainer) {
            createChatContainer();
            loadConversations();
        }
        
        toggleMinimize();
    }
    
    function toggleMinimize() {
        isMinimized = !isMinimized;
        
        if (chatContainer) {
            if (isMinimized) {
                chatContainer.classList.add('minimized');
            } else {
                chatContainer.classList.remove('minimized');
                // Reset notification badge
                const badge = document.getElementById('chat-notifications');
                if (badge) {
                    badge.style.display = 'none';
                    badge.textContent = '0';
                }
            }
        }
    }
    
    function closeChat() {
        if (chatContainer) {
            chatContainer.style.display = 'none';
        }
    }
    
    function fetchUserInfo() {
        // Assume we have a global variable or data attribute with user role
        currentRole = document.body.getAttribute('data-user-role') || '';
        
        if (!currentRole) {
            console.error('User role not found');
        }
    }
    
    function loadConversations() {
        if (isLoading) return;
        isLoading = true;
        
        chatSidebar.innerHTML = '<div class="p-3 text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        
        fetch('/shopverse/api/chat.php?action=getConversations')
            .then(response => response.json())
            .then(data => {
                isLoading = false;
                
                if (data.success) {
                    conversations = data.conversations;
                    renderConversations();
                } else {
                    chatSidebar.innerHTML = `<div class="p-3 text-center text-danger">${data.message}</div>`;
                }
            })
            .catch(error => {
                isLoading = false;
                console.error('Error fetching conversations:', error);
                chatSidebar.innerHTML = '<div class="p-3 text-center text-danger">Gagal memuat percakapan</div>';
            });
    }
    
    function renderConversations() {
        chatSidebar.innerHTML = '';
        
        if (currentRole === 'vendor') {
            // Vendor hanya bisa melihat percakapan yang ada
            if (conversations.length === 0) {
                chatSidebar.innerHTML = '<div class="p-3 text-center">Belum ada percakapan</div>';
                return;
            }
        } else {
            // Customer bisa memulai percakapan baru
            const newChatButton = document.createElement('div');
            newChatButton.className = 'conversation-item';
            newChatButton.innerHTML = '<div class="text-center text-primary"><i class="fas fa-plus-circle mr-2"></i> Mulai Chat Baru</div>';
            newChatButton.addEventListener('click', showVendorList);
            chatSidebar.appendChild(newChatButton);
            
            if (conversations.length === 0) {
                chatSidebar.innerHTML += '<div class="p-3 text-center">Belum ada percakapan</div>';
                return;
            }
        }
        
        conversations.forEach(conversation => {
            const item = document.createElement('div');
            item.className = 'conversation-item';
            if (activeConversationId === conversation.id) {
                item.classList.add('active');
            }
            
            let name, unreadCount;
            
            if (currentRole === 'customer') {
                name = conversation.vendor_name || conversation.vendor_username || 'Vendor';
                unreadCount = conversation.unread_customer;
            } else {
                name = conversation.customer_name || 'Customer';
                unreadCount = conversation.unread_vendor;
            }
            
            let unreadBadge = unreadCount > 0 ? `<span class="unread">${unreadCount}</span>` : '';
            
            item.innerHTML = `
                <div class="name">${name} ${unreadBadge}</div>
                <div class="last-message">${conversation.last_message || 'Belum ada pesan'}</div>
            `;
            
            item.addEventListener('click', () => openConversation(conversation.id));
            chatSidebar.appendChild(item);
        });
    }
    
    function showVendorList() {
        chatSidebar.innerHTML = '<div class="p-3 text-center"><i class="fas fa-spinner fa-spin"></i> Loading vendors...</div>';
        
        // Fetch vendors
        fetch('/shopverse/api/vendors.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderVendorList(data.vendors);
                } else {
                    chatSidebar.innerHTML = `<div class="p-3 text-center text-danger">${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error fetching vendors:', error);
                chatSidebar.innerHTML = '<div class="p-3 text-center text-danger">Gagal memuat daftar vendor</div>';
            });
    }
    
    function renderVendorList(vendors) {
        chatSidebar.innerHTML = `
            <div class="p-2">
                <div class="back-to-conversations"><i class="fas fa-arrow-left"></i> Kembali</div>
                <h6 class="font-weight-bold">Pilih Vendor</h6>
            </div>
        `;
        
        const backButton = chatSidebar.querySelector('.back-to-conversations');
        backButton.addEventListener('click', loadConversations);
        
        if (vendors.length === 0) {
            chatSidebar.innerHTML += '<div class="p-3 text-center">Tidak ada vendor tersedia</div>';
            return;
        }
        
        vendors.forEach(vendor => {
            const item = document.createElement('div');
            item.className = 'conversation-item';
            item.innerHTML = `
                <div class="name">${vendor.shop_name}</div>
                <div class="last-message">Mulai percakapan dengan vendor ini</div>
            `;
            
            item.addEventListener('click', () => startNewConversation(vendor.id));
            chatSidebar.appendChild(item);
        });
    }
    
    function startNewConversation(vendorId) {
        // Cek apakah sudah ada conversation dengan vendor ini
        const existingConversation = conversations.find(c => c.vendor_id == vendorId);
        
        if (existingConversation) {
            openConversation(existingConversation.id);
            return;
        }
        
        // Tampilkan form input pesan
        chatSidebar.style.display = 'none';
        chatConversation.style.display = 'flex';
        
        chatMessages.innerHTML = `
            <div class="back-to-conversations"><i class="fas fa-arrow-left"></i> Kembali</div>
            <div class="text-center p-3">
                <p>Mulai percakapan baru dengan vendor ini</p>
                <p>Ketik pesan pertama Anda di bawah</p>
            </div>
        `;
        
        const backButton = chatMessages.querySelector('.back-to-conversations');
        backButton.addEventListener('click', () => {
            chatSidebar.style.display = 'block';
            chatConversation.style.display = 'none';
            loadConversations();
        });
        
        // Set temporary conversation id
        activeConversationId = 0;
        
        // Set vendor id for new conversation
        chatInputText.dataset.vendorId = vendorId;
    }
    
    function openConversation(conversationId) {
        if (isLoading) return;
        isLoading = true;
        
        activeConversationId = conversationId;
        
        // Render conversations with active conversation highlighted
        renderConversations();
        
        // Show conversation area, hide sidebar
        chatSidebar.style.display = 'none';
        chatConversation.style.display = 'flex';
        
        chatMessages.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>';
        
        fetch(`/shopverse/api/chat.php?action=getMessages&conversation_id=${conversationId}`)
            .then(response => response.json())
            .then(data => {
                isLoading = false;
                
                if (data.success) {
                    messages = data.messages;
                    renderMessages();
                } else {
                    chatMessages.innerHTML = `<div class="p-3 text-center text-danger">${data.message}</div>`;
                }
            })
            .catch(error => {
                isLoading = false;
                console.error('Error fetching messages:', error);
                chatMessages.innerHTML = '<div class="p-3 text-center text-danger">Gagal memuat pesan</div>';
            });
    }
    
    function renderMessages() {
        chatMessages.innerHTML = `
            <div class="back-to-conversations"><i class="fas fa-arrow-left"></i> Kembali</div>
        `;
        
        const backButton = chatMessages.querySelector('.back-to-conversations');
        backButton.addEventListener('click', () => {
            chatSidebar.style.display = 'block';
            chatConversation.style.display = 'none';
            activeConversationId = null;
        });
        
        if (messages.length === 0) {
            chatMessages.innerHTML += '<div class="text-center p-3">Belum ada pesan</div>';
            return;
        }
        
        messages.forEach(message => {
            const isCurrentUser = (currentRole === 'customer' && message.sender_type === 'customer') ||
                                 (currentRole === 'vendor' && message.sender_type === 'vendor');
            
            const messageDiv = document.createElement('div');
            messageDiv.className = isCurrentUser ? 'message sent' : 'message received';
            
            const time = new Date(message.created_at);
            const formattedTime = `${time.getHours().toString().padStart(2, '0')}:${time.getMinutes().toString().padStart(2, '0')}`;
            
            messageDiv.innerHTML = `
                <div class="sender">${message.sender_name}</div>
                <div class="content">${message.message}</div>
                <div class="time">${formattedTime}</div>
            `;
            
            chatMessages.appendChild(messageDiv);
        });
        
        // Scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    function sendMessage() {
        const message = chatInputText.value.trim();
        
        if (!message) return;
        
        // Clear input
        chatInputText.value = '';
        
        const formData = new FormData();
        formData.append('action', 'sendMessage');
        formData.append('message', message);
        
        if (activeConversationId) {
            formData.append('conversation_id', activeConversationId);
        } else if (chatInputText.dataset.vendorId) {
            formData.append('vendor_id', chatInputText.dataset.vendorId);
        } else {
            console.error('No active conversation or vendor ID');
            return;
        }
        
        fetch('/shopverse/api/chat.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (activeConversationId === 0) {
                    // New conversation created
                    activeConversationId = data.conversation_id;
                }
                
                // Add temporary message
                const now = new Date();
                const tempMessage = {
                    sender_type: currentRole,
                    sender_name: 'Me',
                    message: message,
                    created_at: now.toISOString(),
                    is_read: 0
                };
                
                messages.push(tempMessage);
                renderMessages();
                
                // Refresh conversation after sending message
                setTimeout(() => {
                    openConversation(activeConversationId);
                }, 500);
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error sending message:', error);
            alert('Failed to send message');
        });
    }
    
    function pollNewMessages() {
        const currentTime = Date.now();
        
        // Don't poll too frequently
        if (currentTime - lastPollTime < pollInterval) {
            return;
        }
        
        lastPollTime = currentTime;
        
        // If chat is open and we have an active conversation, update it
        if (chatContainer && !isMinimized && activeConversationId) {
            openConversation(activeConversationId);
        }
        
        // Always check for new messages
        checkUnreadMessages();
    }
    
    function checkUnreadMessages() {
        fetch('/shopverse/api/chat.php?action=getConversations')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    conversations = data.conversations;
                    
                    // Update conversation list if displayed
                    if (chatContainer && !isMinimized && !activeConversationId) {
                        renderConversations();
                    }
                    
                    // Update notification badge
                    let totalUnread = 0;
                    
                    conversations.forEach(conversation => {
                        if (currentRole === 'customer') {
                            totalUnread += parseInt(conversation.unread_customer || 0);
                        } else {
                            totalUnread += parseInt(conversation.unread_vendor || 0);
                        }
                    });
                    
                    const badge = document.getElementById('chat-notifications');
                    if (badge) {
                        if (totalUnread > 0) {
                            badge.style.display = 'flex';
                            badge.textContent = totalUnread;
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error checking unread messages:', error);
            });
    }
    
    // API helper for vendors (for new conversations)
    if (!window.chatAPI) {
        window.chatAPI = {
            startChatWithVendor: function(vendorId) {
                if (!chatContainer) {
                    createChatContainer();
                    loadConversations();
                }
                
                // Make sure chat is visible
                isMinimized = false;
                chatContainer.classList.remove('minimized');
                
                // Start new conversation
                startNewConversation(vendorId);
            }
        };
    }
    
    // Initialize chat
    initializeChat();
});