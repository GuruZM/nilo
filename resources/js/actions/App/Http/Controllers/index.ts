import Auth from './Auth';
import CompanyController from './CompanyController';
import DashboardController from './DashboardController';
import Settings from './Settings';

const Controllers = {
    Auth: Object.assign(Auth, Auth),
    DashboardController: Object.assign(
        DashboardController,
        DashboardController,
    ),
    CompanyController: Object.assign(CompanyController, CompanyController),
    Settings: Object.assign(Settings, Settings),
};

export default Controllers;
