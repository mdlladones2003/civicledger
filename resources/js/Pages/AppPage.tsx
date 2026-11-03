import { usePage } from '@inertiajs/react';
import type { Data } from '@/types/app';
import Home from '@/Pages/Public/Home';
import Login from '@/Pages/Auth/Login';
import Register from '@/Pages/Auth/Register';
import AccountProfile from '@/Pages/Account/Profile';
import Dashboard from '@/Pages/Dashboard';
import EventsIndex from '@/Pages/Events/Index';
import EventShow from '@/Pages/Events/Show';
import EventEditor from '@/Pages/Events/Editor';
import AttendanceScan from '@/Pages/Attendance/Scan';
import MyHours from '@/Pages/Student/MyHours';
import ReviewQueue from '@/Pages/Hours/ReviewQueue';
import ReviewDetail from '@/Pages/Hours/ReviewDetail';
import Proofs from '@/Pages/Proofs/Index';
import Certificates from '@/Pages/Certificates/Index';
import Reports from '@/Pages/Reports/Index';
import AdminUsers from '@/Pages/Admin/Users';
import Audit from '@/Pages/Admin/Audit';
import LiveRoster from '@/Pages/Sessions/LiveQr';
import PublicVerify from '@/Pages/Public/Verify';
import DesignSystem from '@/Pages/DesignSystem';
import NotFoundPage from '@/Pages/NotFound';
import { object } from '@/lib/app';

export default function AppPage() {
    const page = usePage();
    const props = page.props as Data;

    switch (page.component) {
        case 'Public/Home':
            return <Home />;
        case 'Auth/Login':
            return <Login />;
        case 'Auth/Register':
            return <Register />;
        case 'Account/Profile':
            return <AccountProfile props={props} />;
        case 'Public/Verify':
            return <PublicVerify props={props} />;
        case 'DesignSystem':
            return <DesignSystem />;
        case 'Student/Dashboard':
            return <Dashboard coordinator={false} summary={object(props.summary)} />;
        case 'Coordinator/Dashboard':
            return <Dashboard coordinator summary={object(props.summary)} />;
        case 'Signer/Dashboard':
            return <Dashboard signer summary={object(props.summary)} />;
        case 'Admin/Dashboard':
            return <Dashboard admin summary={object(props.summary)} />;
        case 'Events/Index':
            return <EventsIndex props={props} />;
        case 'Events/Show':
            return <EventShow props={props} />;
        case 'Events/Editor':
            return <EventEditor props={props} />;
        case 'Attendance/Scan':
            return <AttendanceScan />;
        case 'Student/MyHours':
            return <MyHours props={props} />;
        case 'Hours/ReviewQueue':
            return <ReviewQueue props={props} />;
        case 'Hours/ReviewDetail':
            return <ReviewDetail props={props} />;
        case 'Proofs/Index':
            return <Proofs props={props} detail={false} />;
        case 'Proofs/Show':
            return <Proofs props={props} detail />;
        case 'Certificates/Mine':
            return <Certificates props={props} signer={false} />;
        case 'Certificates/Signer':
            return <Certificates props={props} signer />;
        case 'Certificates/Index':
            return <Certificates props={props} signer={false} admin />;
        case 'Reports/Index':
            return <Reports props={props} />;
        case 'Admin/Users':
            return <AdminUsers props={props} />;
        case 'Admin/Audit':
            return <Audit props={props} />;
        case 'Sessions/LiveQr':
            return <LiveRoster props={props} />;
        default:
            return <NotFoundPage />;
    }
}
