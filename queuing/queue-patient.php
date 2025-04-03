<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queueing Status</title>
    <link rel="stylesheet" href="queue-patient.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        .waiting {
            background-color: #ffcc00;
        }
        .completed {
            background-color: #90ee90;
        }
        .consulting {
            background-color: #87CEFA;
        }
        
        /* Voice selection control styles */
        .voice-control {
            margin: 20px 0;
            padding: 10px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .voice-selector {
            margin: 10px 0;
        }
        select {
            padding: 5px;
            width: 300px;
        }
        .voice-control {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Add audio element for call sound -->
    <audio id="call-sound" src="../assets/sound/call.mp3" preload="auto"></audio>

    <h1>Queueing Status</h1>
    
    <!-- Add voice control panel -->
    <div class="voice-control">
        <h3>Voice Settings</h3>
        <div class="voice-selector">
            <label for="voice-select">Select a female voice: </label>
            <select id="voice-select"></select>
        </div>
        <button id="test-voice">Test Voice</button>
        <div id="voice-status">Loading voices...</div>
    </div>

    <div class="container">
        <h2>Queue List</h2>
        <img src="../assets/img/speaker.png" alt="Speak" width="auto" height="30px" id="speak-again">
    </div>
    <table id="queue-list">
        <thead>
            <tr>
                <th>Queue Number</th>
                <th>Name</th>
                <th>Service</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>

    <h2>Completed List</h2>
    <table id="completed-list">
        <thead>
            <tr>
                <th>Queue Number</th>
                <th>Name</th>
                <th>Service</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <!-- Completed list rows will be populated here -->
        </tbody>
    </table>

    <script>
        // Debug mode - set to true to see more console logs
        const DEBUG = true;
        
        // Create a debug log function to conditionally log based on DEBUG flag
        function debugLog(...args) {
            if (DEBUG) {
                console.log(...args);
            }
        }
        
        // Global variables
        const spokenPatients = new Set(); // Track patients whose statuses have been announced
        let previousPatientStates = {}; // Track previous states to detect changes
        let announcementInProgress = false; // Flag to track if an announcement is currently in progress
        
        // Get DOM elements
        const callSound = document.getElementById('call-sound');
        const voiceSelect = document.getElementById('voice-select');
        const voiceStatus = document.getElementById('voice-status');
        const testVoiceBtn = document.getElementById('test-voice');
        
        // Store the user's selected voice
        let selectedVoice = null;
        
        // Create a new audio context
        let audioContext;
        try {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
            debugLog('Audio context created successfully');
        } catch (e) {
            console.error('Web Audio API is not supported in this browser', e);
        }
        
        // Ensure audio can play (handles autoplay restrictions)
        function setupAudio() {
            // Function to initialize audio context - needs to be called from a user interaction
            if (audioContext && audioContext.state === 'suspended') {
                audioContext.resume().then(() => {
                    debugLog('AudioContext resumed successfully');
                }).catch(error => {
                    console.error('Failed to resume AudioContext:', error);
                });
            }
            
            // Also try to load the audio file
            callSound.load();
        }
        
        // Set up event to initialize audio on first user interaction
        document.addEventListener('click', function initAudio() {
            setupAudio();
            document.removeEventListener('click', initAudio);
            debugLog('Audio initialized on user interaction');
        }, { once: true });
        
        // Pre-load the call sound to ensure it's cached
        callSound.addEventListener('canplaythrough', () => {
            debugLog('Call sound loaded and ready to play');
        }, { once: true });
        
        // List of common female voice identifiers across different systems
        const femaleVoiceIdentifiers = [
            'female', 'woman', 'girl',
            'zira', 'samantha', 'victoria', 'karen', 'moira',
            'tessa', 'veena', 'fiona', 'amelie', 'lisa', 'anna',
            'joana', 'laura', 'nora'
        ];

        // Function to check if a voice is likely female based on name/identifier
        function isFemaleVoice(voice) {
            const voiceName = voice.name.toLowerCase();
            return femaleVoiceIdentifiers.some(identifier => 
                voiceName.includes(identifier.toLowerCase()));
        }

        function populateVoiceList() {
            const voices = speechSynthesis.getVoices();
            
            // Clear existing options
            voiceSelect.innerHTML = '';
            
            // Filter for likely female voices
            const femaleVoices = voices.filter(isFemaleVoice);
            
            // If we found female voices, use those
            let voicesToShow = femaleVoices.length > 0 ? femaleVoices : voices;
            
            // Add all voices to the dropdown
            voicesToShow.forEach(voice => {
                const option = document.createElement('option');
                option.value = voice.name;
                option.textContent = `${voice.name} (${voice.lang})`;
                
                // If it's a likely female voice, mark it
                if (isFemaleVoice(voice)) {
                    option.textContent += ' - Female';
                }
                
                voiceSelect.appendChild(option);
            });
            
            // If we found female voices, update status
            if (femaleVoices.length > 0) {
                voiceStatus.textContent = `Found ${femaleVoices.length} female voices`;
                
                // Preselect the first female voice
                voiceSelect.value = femaleVoices[0].name;
                selectedVoice = femaleVoices[0];
                
                // Save to localStorage
                localStorage.setItem('selectedVoiceName', femaleVoices[0].name);
            } else {
                voiceStatus.textContent = 'No female voices detected. Please select any voice and it will be adjusted to sound more feminine.';
                
                // Preselect the first voice
                if (voices.length > 0) {
                    voiceSelect.value = voices[0].name;
                    selectedVoice = voices[0];
                    localStorage.setItem('selectedVoiceName', voices[0].name);
                }
            }
        }

        function getCurrentVoice() {
            // If we have a selected voice, use it
            if (selectedVoice) {
                return selectedVoice;
            }
            
            // Otherwise, try to find the voice selected in the dropdown
            const voices = speechSynthesis.getVoices();
            const selectedName = voiceSelect.value;
            
            if (selectedName) {
                const voice = voices.find(v => v.name === selectedName);
                if (voice) {
                    selectedVoice = voice;
                    return voice;
                }
            }
            
            // Fall back to any female voice we can find
            const femaleVoice = voices.find(isFemaleVoice);
            if (femaleVoice) {
                selectedVoice = femaleVoice;
                return femaleVoice;
            }
            
            // Last resort: first voice in the list
            if (voices.length > 0) {
                selectedVoice = voices[0];
                return voices[0];
            }
            
            return null;
        }

        function speak(text, callback) {
            if (speechSynthesis.speaking) {
                debugLog("Speech already in progress, canceling...");
                speechSynthesis.cancel();
            }

            const utterance = new SpeechSynthesisUtterance(text);
            const voice = getCurrentVoice();
            
            if (voice) {
                utterance.voice = voice;
                debugLog(`Using voice: ${voice.name}`);
            } else {
                console.warn('No voice found, using default voice.');
            }

            // Adjust pitch and rate
            utterance.pitch = 1.1;  // Slightly higher pitch
            utterance.rate = 1;     // Normal rate

            utterance.onstart = () => debugLog(`Speaking: ${text}`);
            utterance.onerror = (event) => console.error('Speech synthesis error:', event);
            
            if (callback) {
                utterance.onend = callback; // Call the callback when the speech ends
            }
            
            speechSynthesis.speak(utterance);
        }

        // Play the call sound with a callback for when it's done
        function playCallSound(onComplete) {
            debugLog('Attempting to play call sound...');
            
            // Make sure audio context is running
            if (audioContext && audioContext.state === 'suspended') {
                audioContext.resume();
            }
            
            // Create a new separate audio element to avoid conflicts
            const tempSound = new Audio(callSound.src);
            tempSound.volume = 1.0;  // Ensure volume is up
            
            // Set up event handlers
            tempSound.onplay = () => debugLog('Call sound is playing');
            tempSound.onended = () => {
                debugLog('Call sound finished playing');
                if (onComplete) onComplete();
            };
            tempSound.onerror = (e) => {
                console.error('Error playing sound:', e);
                // Still call the callback if there's an error
                if (onComplete) onComplete();
            };
            
            // Try to play the sound with both the Promise API and event handlers
            try {
                const playPromise = tempSound.play();
                
                // Modern browsers return a promise from play()
                if (playPromise !== undefined) {
                    playPromise.catch(error => {
                        console.error('Error playing sound (promise):', error);
                        
                        // If the promise fails (e.g., autoplay restrictions), try a different approach:
                        // 1. Use a user interaction to play the sound
                        document.addEventListener('click', function tryPlayOnClick() {
                            document.removeEventListener('click', tryPlayOnClick);
                            tempSound.play().catch(e => {
                                console.error('Still failed to play after click:', e);
                                if (onComplete) onComplete();
                            });
                        }, { once: true });
                        
                        // 2. But also continue with speech after a timeout in case user doesn't click
                        setTimeout(() => {
                            if (onComplete) onComplete();
                        }, 1000);
                    });
                }
            } catch (e) {
                console.error('Exception trying to play sound:', e);
                // If play() throws an error, still call the callback
                if (onComplete) onComplete();
            }
        }

        function announcePatient(patient) {
            // Prevent multiple simultaneous announcements
            if (announcementInProgress) {
                debugLog('Announcement already in progress, not starting a new one');
                return;
            }
            
            announcementInProgress = true;
            
            const textToSpeak = `Patient ${patient.name} Queue Number ${patient.queue_number}, please proceed to ${patient.service}`;
            debugLog(`Announcing patient: ${textToSpeak}`);
            
            // First play the call sound, then speak
            playCallSound(() => {
                // After sound finishes (or fails), make the announcement
                let count = 0;
                
                const speakThreeTimes = () => {
                    if (count < 3) {
                        speak(textToSpeak, () => {
                            count++;
                            if (count < 3) {
                                setTimeout(speakThreeTimes, 1000); // Delay before next speech
                            } else {
                                // Reset the flag when all announcements are complete
                                announcementInProgress = false;
                            }
                        });
                    } else {
                        // Reset the flag when all announcements are complete
                        announcementInProgress = false;
                    }
                };
                
                speakThreeTimes();
            });
            
            // Mark this patient as announced
            spokenPatients.add(patient.id);
        }

        function fetchPatientLists() {
            fetch('get_queue.php')
                .then(response => response.json())
                .then(data => {
                    const queueTableBody = document.querySelector('#queue-list tbody');
                    const completedTableBody = document.querySelector('#completed-list tbody');

                    queueTableBody.innerHTML = '';
                    completedTableBody.innerHTML = '';

                    debugLog("Fetched patient data:", data);

                    // Process queue list
                    data.queueList.forEach(patient => {
                        const row = document.createElement('tr');
                        row.classList.add(patient.status.toLowerCase());
                        row.innerHTML = `
                            <td>${patient.queue_number}</td>
                            <td>${patient.name}</td>
                            <td>${patient.service}</td>
                            <td>${patient.status}</td>
                        `;
                        queueTableBody.appendChild(row);

                        // Check if this patient's status has changed to "Consulting"
                        const previousState = previousPatientStates[patient.id];
                        const isNewConsulting = patient.status === 'Consulting' && 
                                               (!previousState || previousState.status !== 'Consulting');
                                               
                        if (isNewConsulting) {
                            debugLog(`STATUS CHANGE DETECTED: Patient ${patient.name} is now Consulting`);
                            
                            // Only announce if we're not already in the middle of an announcement
                            if (!announcementInProgress) {
                                announcePatient(patient);
                            } else {
                                debugLog('Announcement in progress, queueing this patient for later');
                                // Could implement a queue system here if needed
                            }
                        }
                        
                        // Update previous state
                        previousPatientStates[patient.id] = { 
                            status: patient.status,
                            name: patient.name,
                            service: patient.service,
                            queue_number: patient.queue_number
                        };
                    });

                    // Process completed list
                    data.completedList.forEach(patient => {
                        const row = document.createElement('tr');
                        row.classList.add(patient.status.toLowerCase());
                        row.innerHTML = `
                            <td>${patient.queue_number}</td>
                            <td>${patient.name}</td>
                            <td>${patient.service}</td>
                            <td>${patient.status}</td>
                        `;
                        completedTableBody.appendChild(row);
                        
                        // Update previous state for completed patients too
                        previousPatientStates[patient.id] = { 
                            status: patient.status,
                            name: patient.name,
                            service: patient.service,
                            queue_number: patient.queue_number
                        };
                    });
                })
                .catch(error => console.error('Error fetching patient lists:', error));
        }

        // Initialize voice selection related events
        function initVoiceSelection() {
            // Handle voice selection change
            voiceSelect.addEventListener('change', function() {
                const voices = speechSynthesis.getVoices();
                const selectedName = this.value;
                
                selectedVoice = voices.find(voice => voice.name === selectedName);
                
                if (selectedVoice) {
                    debugLog(`Voice changed to: ${selectedVoice.name}`);
                    // Save selection to localStorage
                    localStorage.setItem('selectedVoiceName', selectedName);
                }
            });
            
            // Test voice button
            testVoiceBtn.addEventListener('click', function() {
                const testText = "This is a test of the selected voice. Is this voice female enough?";
                speak(testText);
                
                // Also test call sound
                debugLog('Testing call sound...');
                playCallSound(() => {
                    debugLog('Call sound test complete');
                });
            });
            
            // Try to load previously selected voice
            const savedVoiceName = localStorage.getItem('selectedVoiceName');
            if (savedVoiceName) {
                // We'll set this when voices are available
                debugLog(`Attempting to restore saved voice: ${savedVoiceName}`);
            }
        }

        // Initialize voices and populate voice list when they're loaded
        function initVoices() {
            // If voices are already available
            if (speechSynthesis.getVoices().length > 0) {
                populateVoiceList();
                
                // Try to restore saved voice
                const savedVoiceName = localStorage.getItem('selectedVoiceName');
                if (savedVoiceName) {
                    const voices = speechSynthesis.getVoices();
                    const savedVoice = voices.find(v => v.name === savedVoiceName);
                    if (savedVoice) {
                        voiceSelect.value = savedVoiceName;
                        selectedVoice = savedVoice;
                        debugLog(`Restored saved voice: ${savedVoiceName}`);
                    }
                }
            }
            
            // For when voices load later
            speechSynthesis.onvoiceschanged = function() {
                populateVoiceList();
                
                // Try to restore saved voice
                const savedVoiceName = localStorage.getItem('selectedVoiceName');
                if (savedVoiceName) {
                    const voices = speechSynthesis.getVoices();
                    const savedVoice = voices.find(v => v.name === savedVoiceName);
                    if (savedVoice) {
                        voiceSelect.value = savedVoiceName;
                        selectedVoice = savedVoice;
                        debugLog(`Restored saved voice: ${savedVoiceName}`);
                    }
                }
            };
        }

        // Initialize
        initVoiceSelection();
        initVoices();

        // Fetch patient lists immediately and every 5 seconds
        fetchPatientLists();
        setInterval(fetchPatientLists, 5000);

        // Speak again button functionality
        document.getElementById('speak-again').addEventListener('click', function() {
            // This will also initialize audio if it hasn't been already
            setupAudio();
            
            const queueRows = document.querySelectorAll('#queue-list tbody tr');
            let consultingPatientFound = false;
            
            queueRows.forEach(row => {
                const status = row.cells[3].innerText;
                if (status === 'Consulting') {
                    consultingPatientFound = true;
                    const queueNumber = row.cells[0].innerText; // Get Queue Number
                    const name = row.cells[1].innerText; // Get Name
                    const service = row.cells[2].innerText; // Get Service

                    // Create a patient object for the announcePatient function
                    const patient = {
                        queue_number: queueNumber,
                        name: name, 
                        service: service
                    };
                    
                    // Use the same announce function for consistency
                    announcePatient(patient);
                    
                    // Only announce the first consulting patient found
                    return;
                }
            });
            
            if (!consultingPatientFound) {
                debugLog("No patients with 'Consulting' status found");
                alert("No patients currently being consulted.");
            }
        });
        
        // Add console message to confirm script loaded
        console.log("Queue system script loaded and initialized");
    </script>

</body>
</html>