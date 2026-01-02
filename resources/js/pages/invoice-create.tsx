import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function InvoiceBuilder() {
    const [items, setItems] = useState([
        { description: '', quantity: 1, price: 0 },
    ]);
    const [client, setClient] = useState('');
    const [date, setDate] = useState('');

    const addItem = () =>
        setItems([...items, { description: '', quantity: 1, price: 0 }]);
    const updateItem = (idx: number, field: string, value: any) => {
        setItems(
            items.map((item, i) =>
                i === idx ? { ...item, [field]: value } : item,
            ),
        );
    };
    const removeItem = (idx: number) =>
        setItems(items.filter((_, i) => i !== idx));

    const total = items.reduce(
        (sum, item) => sum + item.quantity * item.price,
        0,
    );

    return (
        <AppLayout>
            <Head title="Create Invoice" />
            <div className="mx-auto max-w-2xl rounded bg-white p-6 shadow">
                <h1 className="mb-4 text-2xl font-bold">Create Invoice</h1>
                <div className="mb-4">
                    <label className="mb-1 block font-medium">Client</label>
                    <input
                        value={client}
                        onChange={(e) => setClient(e.target.value)}
                        className="w-full rounded border px-3 py-2"
                    />
                </div>
                <div className="mb-4">
                    <label className="mb-1 block font-medium">Date</label>
                    <input
                        type="date"
                        value={date}
                        onChange={(e) => setDate(e.target.value)}
                        className="w-full rounded border px-3 py-2"
                    />
                </div>
                <div>
                    <label className="mb-2 block font-medium">Items</label>
                    {items.map((item, idx) => (
                        <div key={idx} className="mb-2 flex gap-2">
                            <input
                                placeholder="Description"
                                value={item.description}
                                onChange={(e) =>
                                    updateItem(
                                        idx,
                                        'description',
                                        e.target.value,
                                    )
                                }
                                className="flex-1 rounded border px-2 py-1"
                            />
                            <input
                                type="number"
                                min={1}
                                value={item.quantity}
                                onChange={(e) =>
                                    updateItem(
                                        idx,
                                        'quantity',
                                        Number(e.target.value),
                                    )
                                }
                                className="w-20 rounded border px-2 py-1"
                            />
                            <input
                                type="number"
                                min={0}
                                value={item.price}
                                onChange={(e) =>
                                    updateItem(
                                        idx,
                                        'price',
                                        Number(e.target.value),
                                    )
                                }
                                className="w-24 rounded border px-2 py-1"
                            />
                            <button
                                type="button"
                                onClick={() => removeItem(idx)}
                                className="text-red-500"
                            >
                                &times;
                            </button>
                        </div>
                    ))}
                    <button
                        type="button"
                        onClick={addItem}
                        className="mt-2 rounded bg-blue-600 px-3 py-1 text-white"
                    >
                        Add Item
                    </button>
                </div>
                <div className="mt-6 text-right text-lg font-bold">
                    Total: ${total.toFixed(2)}
                </div>
                <button className="mt-6 w-full rounded bg-green-600 py-2 font-semibold text-white">
                    Save Invoice
                </button>
            </div>
        </AppLayout>
    );
}
