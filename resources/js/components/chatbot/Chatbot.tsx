import { Bot, MessageCircle, Minimize2, Send } from 'lucide-react';
import React, { useState } from 'react';
interface ChatMessage {
    id: string;
    text: string;
    isBot: boolean;
    timestamp: Date;
}

const Chatbot: React.FC = () => {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState<ChatMessage[]>([
        {
            id: '1',
            text: 'Hi, my name is Nilo, your AI invoice assistant. How can I help you today?',
            isBot: true,
            timestamp: new Date(),
        },
    ]);
    const [inputMessage, setInputMessage] = useState('');
    const [isTyping, setIsTyping] = useState(false);

    const botResponses = [
        {
            keywords: ['price', 'pricing', 'cost', 'plan'],
            response:
                'Our pricing is simple: Free forever plan, Professional at $19/month, and Enterprise at $49/month. All plans include a 14-day free trial!',
        },
        {
            keywords: ['feature', 'what', 'can', 'do'],
            response:
                'Nilo offers unlimited invoices, beautiful templates, payment processing, client management, reporting, and much more!',
        },
        {
            keywords: ['trial', 'free', 'demo'],
            response:
                'Yes! We offer a 14-day free trial with no credit card required. You can try all Professional features risk-free.',
        },
        {
            keywords: ['support', 'help', 'contact'],
            response:
                'We provide email support for all users, priority support for Professional users, and dedicated support for Enterprise. You can reach us at hello@invoiceflow.com',
        },
        {
            keywords: ['integration', 'connect', 'api'],
            response:
                'We integrate with popular payment processors like Stripe, and offer API access for Enterprise customers. More integrations coming soon!',
        },
        {
            keywords: ['security', 'safe', 'data'],
            response:
                'Your data is protected with bank-level encryption, regular backups, and 99.9% uptime. We take security very seriously!',
        },
        {
            keywords: ['hello', 'hi', 'hey'],
            response:
                'Hi, my name is Nilo, your AI invoice assistant. What would you like to know?',
        },
    ];

    const sendMessage = () => {
        if (!inputMessage.trim()) return;

        const userMessage: ChatMessage = {
            id: Date.now().toString(),
            text: inputMessage,
            isBot: false,
            timestamp: new Date(),
        };

        setMessages((prev) => [...prev, userMessage]);
        setInputMessage('');
        setIsTyping(true);

        // Simulate bot response delay
        setTimeout(() => {
            const lowerInput = inputMessage.toLowerCase();
            const matchedResponse = botResponses.find((response) =>
                response.keywords.some((keyword) =>
                    lowerInput.includes(keyword),
                ),
            );

            const botMessage: ChatMessage = {
                id: (Date.now() + 1).toString(),
                text:
                    matchedResponse?.response ||
                    'Thanks for your message! For specific questions, please contact our support team at hello@nilo.com or use the contact form below.',
                isBot: true,
                timestamp: new Date(),
            };

            setMessages((prev) => [...prev, botMessage]);
            setIsTyping(false);
        }, 1000);
    };

    const handleKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    return (
        <div className="fixed right-6 bottom-6 z-50">
            {!isOpen ? (
                <div className="group relative">
                    <button
                        onClick={() => setIsOpen(true)}
                        className="transform rounded-full bg-[#00417d] p-4 text-white shadow-lg transition-all duration-300 hover:scale-110 hover:shadow-xl"
                        aria-label="Open chatbot"
                    >
                        <MessageCircle className="h-6 w-6" />
                    </button>
                    <span className="pointer-events-none absolute bottom-full left-1/2 mb-2 w-max -translate-x-1/2 scale-0 rounded bg-gray-800 px-2 py-1 text-xs text-white opacity-0 transition-all group-hover:scale-100 group-hover:opacity-100">
                        Chat with your AI assistant
                    </span>
                </div>
            ) : (
                <div className="flex h-96 w-80 flex-col rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-800">
                    {/* Chat Header */}
                    <div className="flex items-center justify-between rounded-t-2xl bg-[#00417d] p-4 text-white">
                        <div className="flex items-start space-x-3">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-white/20">
                                <Bot className="h-4 w-4" />
                            </div>
                            <div>
                                <h4 className="font-semibold">Nilo</h4>
                                <p className="text-xs opacity-90">
                                    Your AI invoice assistant
                                </p>
                            </div>
                        </div>
                        <button
                            onClick={() => setIsOpen(false)}
                            className="text-white/80 transition-colors hover:text-white"
                        >
                            <Minimize2 className="h-5 w-5" />
                        </button>
                    </div>

                    {/* Chat Messages */}
                    <div className="flex-1 space-y-4 overflow-y-auto p-4">
                        {messages.map((message) => (
                            <div
                                key={message.id}
                                className={`flex ${message.isBot ? 'justify-start' : 'justify-end'}`}
                            >
                                <div
                                    className={`max-w-xs rounded-2xl px-4 py-2 ${
                                        message.isBot
                                            ? 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-white'
                                            : 'bg-[#00417d] text-white'
                                    }`}
                                >
                                    <p className="text-sm">{message.text}</p>
                                </div>
                            </div>
                        ))}
                        {isTyping && (
                            <div className="flex justify-start">
                                <div className="rounded-2xl bg-slate-100 px-4 py-2 dark:bg-slate-700">
                                    <div className="flex space-x-1">
                                        <div className="h-2 w-2 animate-bounce rounded-full bg-slate-400"></div>
                                        <div
                                            className="h-2 w-2 animate-bounce rounded-full bg-slate-400"
                                            style={{ animationDelay: '0.1s' }}
                                        ></div>
                                        <div
                                            className="h-2 w-2 animate-bounce rounded-full bg-slate-400"
                                            style={{ animationDelay: '0.2s' }}
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Chat Input */}
                    <div className="border-t border-slate-200 p-4 dark:border-slate-700">
                        <div className="flex space-x-2">
                            <input
                                type="text"
                                value={inputMessage}
                                onChange={(e) =>
                                    setInputMessage(e.target.value)
                                }
                                onKeyPress={handleKeyPress}
                                placeholder="Type your message..."
                                className="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                            <button
                                onClick={sendMessage}
                                className="rounded-lg bg-[#00417d] p-2 text-white transition-all duration-300 hover:shadow-lg"
                            >
                                <Send className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Chatbot;
