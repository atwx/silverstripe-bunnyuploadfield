import Injector from 'lib/Injector';
import BunnyVideoUploadField from '../components/BunnyVideoUploadField/BunnyVideoUploadField';

export default () => {
  Injector.component.registerMany({
    BunnyVideoUploadField,
  });
};
