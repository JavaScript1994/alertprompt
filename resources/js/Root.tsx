import { BrowserRouter, Route, Routes } from 'react-router-dom';
import ProtectedRoute from '@/components/ProtectedRoute';
import RequirePermission from '@/components/RequirePermission';
import AuthLayout from '@/layouts/AuthLayout';
import DashboardLayout from '@/layouts/DashboardLayout';
import Alerts from '@/pages/admin/alerts/Alerts';
import ClientDetail from '@/pages/admin/clients/ClientDetail';
import Clients from '@/pages/admin/clients/Clients';
import Modules from '@/pages/admin/modules/Modules';
import AdminReports from '@/pages/admin/reports/AdminReports';
import RoleEditor from '@/pages/admin/roles/RoleEditor';
import Roles from '@/pages/admin/roles/Roles';
import ForgotPassword from '@/pages/auth/ForgotPassword';
import Login from '@/pages/auth/Login';
import ResetPassword from '@/pages/auth/ResetPassword';
import Campaigns from '@/pages/campaigns/Campaigns';
import Contacts from '@/pages/contacts/Contacts';
import Dashboard from '@/pages/dashboard/Dashboard';
import Reports from '@/pages/reports/Reports';
import AccountSettings from '@/pages/settings/AccountSettings';
import ChannelAccounts from '@/pages/settings/ChannelAccounts';
import Users from '@/pages/settings/Users';
import Templates from '@/pages/templates/Templates';

export default function Root() {
    return (
        <BrowserRouter>
            <Routes>
                <Route element={<AuthLayout />}>
                    <Route path="/login" element={<Login />} />
                    <Route path="/forgot-password" element={<ForgotPassword />} />
                    <Route path="/reset-password" element={<ResetPassword />} />
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

                        <Route element={<RequirePermission permission="reports.view" />}>
                            <Route path="/reports" element={<Reports />} />
                        </Route>
                        <Route element={<RequirePermission permission="settings.view" />}>
                            <Route path="/settings" element={<AccountSettings />} />
                        </Route>
                        <Route element={<RequirePermission permission="whatsapp_account.view" />}>
                            <Route path="/settings/whatsapp" element={<ChannelAccounts />} />
                        </Route>
                        <Route element={<RequirePermission permission="users.view" />}>
                            <Route path="/settings/users" element={<Users />} />
                        </Route>

                        <Route element={<RequirePermission permission="admin.clients.view" />}>
                            <Route path="/admin/clients/companies" element={<Clients key="company" type="company" />} />
                            <Route path="/admin/clients/individuals" element={<Clients key="individual" type="individual" />} />
                            <Route path="/admin/clients/:id" element={<ClientDetail />} />
                        </Route>
                        <Route element={<RequirePermission permission="admin.alerts.view" />}>
                            <Route path="/admin/alerts" element={<Alerts />} />
                        </Route>
                        <Route element={<RequirePermission permission="admin.reports.view" />}>
                            <Route path="/admin/reports" element={<AdminReports />} />
                        </Route>
                        <Route element={<RequirePermission permission="admin.modules.view" />}>
                            <Route path="/admin/modules" element={<Modules />} />
                        </Route>
                        <Route element={<RequirePermission permission="admin.roles.view" />}>
                            <Route path="/admin/roles" element={<Roles />} />
                            <Route path="/admin/roles/:id" element={<RoleEditor />} />
                        </Route>
                        <Route element={<RequirePermission permission="admin.roles.manage" />}>
                            <Route path="/admin/roles/new" element={<RoleEditor />} />
                        </Route>
                    </Route>
                </Route>
            </Routes>
        </BrowserRouter>
    );
}
