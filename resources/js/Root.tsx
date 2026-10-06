import { BrowserRouter, Route, Routes } from 'react-router-dom';
import ProtectedRoute from '@/components/ProtectedRoute';
import AuthLayout from '@/layouts/AuthLayout';
import DashboardLayout from '@/layouts/DashboardLayout';
import Login from '@/pages/auth/Login';
import Campaigns from '@/pages/campaigns/Campaigns';
import Contacts from '@/pages/contacts/Contacts';
import Dashboard from '@/pages/dashboard/Dashboard';
import Templates from '@/pages/templates/Templates';

export default function Root() {
    return (
        <BrowserRouter>
            <Routes>
                <Route element={<AuthLayout />}>
                    <Route path="/login" element={<Login />} />
                </Route>

                <Route element={<ProtectedRoute />}>
                    <Route element={<DashboardLayout />}>
                        <Route path="/" element={<Dashboard />} />
                        <Route path="/contacts" element={<Contacts />} />
                        <Route path="/templates" element={<Templates />} />
                        <Route path="/campaigns" element={<Campaigns />} />
                    </Route>
                </Route>
            </Routes>
        </BrowserRouter>
    );
}
