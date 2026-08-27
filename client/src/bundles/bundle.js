import registerComponents from '../boot/registerComponents';
import '../legacy/entwine';

window.document.addEventListener('DOMContentLoaded', () => {
  registerComponents();
});
