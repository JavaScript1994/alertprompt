import { BrowserRouter, Route, Routes } from 'react-router-dom';
import ProtectedRoute from '@/components/ProtectedRoute';
import RequirePermission from '@/components/RequirePermission';
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
                        <Route element={<RequirePermission permission="dashboard.view" />}>
                            <Route path="/" element={<Dashboard />} />
                        </Route>
                        <Route element={<RequirePermission permission="contacts.view" />}>
                            <Route path="/contacts" element={<Contacts />} />
                        </Route>
                        <Route element={<RequirePermission permission="templates.view" />}>
                            <Route path="/templates" element={<Templates />} />
                        </Route>
                        <Route element={<RequirePermission permission="campaigns.view" />}>
                            <Route path="/campaigns" element={<Campaigns />} />
                        </Route>
                    </Route>
                </Route>
            </Routes>
        </BrowserRouter>
    );
}
