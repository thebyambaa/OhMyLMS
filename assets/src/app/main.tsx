/**
 * Entry point of the typed admin app (webpack entry `admin-app`). See docs/ARCHITECTURE.md.
 */
import './styles/app.css';
import { mountApp } from './bootstrap/mount';

mountApp( document, window.ohmylmsApp );
